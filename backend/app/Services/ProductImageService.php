<?php

namespace App\Services;

use App\Exceptions\BusinessInputException;
use App\Exceptions\BusinessRuleException;
use App\Models\Product;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductImageService
{
    private const MAX_DOWNLOAD_BYTES = 5 * 1024 * 1024;

    /** @var array<string, string> */
    private const EXTENSIONS_BY_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function storeUpload(Product $product, UploadedFile $image): string
    {
        $extension = Str::lower($image->extension() ?: $image->getClientOriginalExtension());
        $filename = $product->id.'-'.Str::uuid().'.'.$extension;
        $path = $image->storeAs('products', $filename, 'public');

        if (! is_string($path)) {
            throw new BusinessRuleException('The product image could not be stored.');
        }

        return $path;
    }

    public function fetchFromBarcode(Product $product): string
    {
        $barcode = trim((string) $product->barcode);
        if ($barcode === '' || ! preg_match('/^\d{8,14}$/', $barcode)) {
            throw new BusinessInputException('A valid numeric barcode is required to fetch a product image.');
        }

        try {
            $productResponse = Http::acceptJson()
                ->withUserAgent(config('app.name').'/1.0 ('.config('app.url').')')
                ->connectTimeout(3)
                ->timeout(8)
                ->retry(2, 200, throw: false)
                ->get("https://world.openfoodfacts.org/api/v2/product/{$barcode}.json", [
                    'fields' => 'code,image_front_url',
                ]);
        } catch (ConnectionException) {
            throw new BusinessRuleException('The product image provider is currently unavailable.');
        }

        $imageUrl = $productResponse->successful()
            ? $productResponse->json('product.image_front_url')
            : null;
        if (! is_string($imageUrl) || ! $this->isTrustedImageUrl($imageUrl)) {
            throw new BusinessRuleException('No trusted image was found for this barcode.');
        }

        try {
            $imageResponse = Http::withUserAgent(config('app.name').'/1.0 ('.config('app.url').')')
                ->connectTimeout(3)
                ->timeout(10)
                ->retry(2, 200, throw: false)
                ->get($imageUrl);
        } catch (ConnectionException) {
            throw new BusinessRuleException('The product image could not be downloaded.');
        }

        $body = $imageResponse->body();
        $mime = Str::before((string) $imageResponse->header('Content-Type'), ';');
        if (! $imageResponse->successful()
            || ! isset(self::EXTENSIONS_BY_MIME[$mime])
            || $body === ''
            || strlen($body) > self::MAX_DOWNLOAD_BYTES) {
            throw new BusinessRuleException('The product image provider returned an invalid image.');
        }

        $path = 'products/'.$product->id.'-'.Str::uuid().'.'.self::EXTENSIONS_BY_MIME[$mime];
        if (! Storage::disk('public')->put($path, $body)) {
            throw new BusinessRuleException('The product image could not be stored.');
        }

        return $path;
    }

    public function deleteLocal(?string $path): void
    {
        if ($path === null || $path === '' || Str::startsWith($path, ['http://', 'https://'])) {
            return;
        }

        Storage::disk('public')->delete($path);
    }

    private function isTrustedImageUrl(string $url): bool
    {
        $parts = parse_url($url);
        $host = Str::lower((string) ($parts['host'] ?? ''));

        return ($parts['scheme'] ?? null) === 'https'
            && ($host === 'images.openfoodfacts.org' || Str::endsWith($host, '.openfoodfacts.org'));
    }
}
