/**
 * Convert a leading-slash absolute URL like "/results/foo.png" into a
 * path-relative URL based on the current page's pathname (e.g. "/yzhrnztzvbn/").
 *
 * - "/results/foo.png" from "/" -> "results/foo.png"
 * - "/results/foo.png" from "/abc/" -> "../results/foo.png"
 * - external URLs (http://, https://, //, data:) and already-relative
 *   URLs are returned unchanged.
 */
export function toRelativeUrl(url: string | undefined, pathname: string): string | undefined {
  if (!url) return undefined;
  if (/^([a-z][a-z0-9+.-]*:)?\/\//i.test(url)) return url; // protocol or //cdn
  if (url.startsWith("data:")) return url;
  if (!url.startsWith("/")) return url;

  const segments = pathname.split("/").filter(Boolean);
  // Each non-empty path segment in the *page's* URL means the HTML lives one
  // level deeper than the dist root. Index pages live at "/<segments>/index.html".
  const depth = segments.length;
  const prefix = depth === 0 ? "" : "../".repeat(depth);
  return prefix + url.replace(/^\/+/, "");
}
