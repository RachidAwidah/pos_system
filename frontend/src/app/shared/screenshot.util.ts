import html2canvas from 'html2canvas';

export async function downloadElementScreenshot(element: HTMLElement, filename: string): Promise<void> {
  await document.fonts.ready;
  // Let the browser paint the exporting state before cloning the DOM.
  await new Promise<void>(resolve => requestAnimationFrame(() => setTimeout(resolve, 0)));

  const direction = getComputedStyle(element).direction;
  const width = Math.ceil(element.getBoundingClientRect().width);
  const ancestors = new Set<Element>();
  for (let parent = element.parentElement; parent; parent = parent.parentElement) {
    ancestors.add(parent);
  }

  const canvas = await html2canvas(element, {
    backgroundColor: '#f7f9fc',
    scale: 2,
    useCORS: true,
    allowTaint: false,
    logging: false,
    removeContainer: true,
    foreignObjectRendering: true,
    windowWidth: window.innerWidth,
    windowHeight: window.innerHeight,
    scrollX: 0,
    scrollY: 0,
    // html2canvas starts at documentElement, even for a single target. Prune
    // unrelated branches before it copies their computed styles and images.
    ignoreElements: candidate => candidate.hasAttribute('data-export-exclude') || (
      !ancestors.has(candidate) && !element.contains(candidate) &&
      candidate !== document.head && !document.head.contains(candidate)
    ),
    onclone: async (clonedDoc, clone) => {
      // The foreign-object renderer copies computed styles. Move only its copy
      // outside Nebular's scrolling layout so ancestors cannot clip the export.
      clonedDoc.body.appendChild(clone);
      clone.style.position = 'absolute';
      clone.style.inset = '0 auto auto 0';
      clone.style.margin = '0';
      clone.style.transform = 'none';
      clone.style.direction = direction;
      clone.style.boxSizing = 'border-box';
      clone.style.width = `${width}px`;
      clone.style.minHeight = '0';

      [clone, ...Array.from(clone.querySelectorAll<HTMLElement>('*'))].forEach(node => {
        if (!node.style) return;
        // Preserve media dimensions, but let copied cards and rows grow to
        // include their full contents, including previously scrolled tables.
        if (!node.matches('img, svg, svg *, canvas, video, input')) {
          node.style.height = 'auto';
          node.style.maxHeight = 'none';
        }
        node.style.overflow = 'visible';
        if (node.style.textOverflow === 'ellipsis') {
          node.style.whiteSpace = 'normal';
          node.style.overflowWrap = 'anywhere';
          node.style.textOverflow = 'clip';
        }
        // Do not set scroll offsets here: doing so after every style change
        // forces synchronous layout for each node. Visible overflow removes
        // the scroll containers without that repeated layout work.
      });
      await clonedDoc.fonts.ready;
      // Measure after expanding the clone, rather than cropping to the live
      // element's old height. html2canvas reads these bounds after onclone.
      clone.style.width = `${Math.max(clone.scrollWidth, Math.ceil(clone.getBoundingClientRect().width))}px`;
      clone.style.height = `${Math.max(clone.scrollHeight, Math.ceil(clone.getBoundingClientRect().height))}px`;
    },
  });

  const blob = await new Promise<Blob>((resolve, reject) => {
    canvas.toBlob(result => result ? resolve(result) : reject(new Error('Unable to create PNG blob.')), 'image/png');
  });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = filename.replace(/\.[^.]+$/, '') + '.png';
  document.body.appendChild(link);
  link.click();
  link.remove();
  window.setTimeout(() => URL.revokeObjectURL(url), 60_000);
}
