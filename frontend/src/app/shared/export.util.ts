import * as XLSX from 'xlsx';

type ExcelCell = string | number | boolean | Date | null | undefined;

export function downloadExcel(filename: string, headers: string[], rows: ExcelCell[][]): void {
  const worksheet = XLSX.utils.aoa_to_sheet([headers, ...rows]);
  worksheet['!cols'] = headers.map((header, columnIndex) => ({
    wch: Math.min(45, Math.max(
      header.length + 2,
      ...rows.map(row => String(row[columnIndex] ?? '').length + 2),
    )),
  }));

  const workbook = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(workbook, worksheet, 'Data');
  XLSX.writeFile(workbook, normalizeXlsxFilename(filename), {
    bookType: 'xlsx',
    compression: true,
  });
}

function normalizeXlsxFilename(filename: string): string {
  const baseName = filename.replace(/\.(csv|xls|xlsx)$/i, '');
  return `${baseName}.xlsx`;
}
