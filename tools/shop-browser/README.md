# Private browser verification prototype

## Native Chrome comparison (current deployment)

The DOM inspector extension is now installed via Chrome's documented Linux local
CRX registration. `install-dom.sh` packs/signs locally; its private key stays on
the server outside the repo. Native messaging accepts only that extension ID.
`capture.js` reads bounded product element text and selected Product JSON-LD
fields every five seconds on supported product URLs only. It never clicks,
fetches other products, reads cookies, or reads form input values/images.
`native_host.py` replaces a private per-shop snapshot; no public receiving port
is opened. `read` rejects stale snapshots and mismatched product/store URLs.
The web UI displays candidates, not authoritative price/availability decisions,
and does not update the database. A pending result means there is no fresh match.

Live checks on 2026-10-07 returned DOM snapshots for all three shops. Surugaya
included a price-buy element of JPY 800 and an old-price element of JPY 1000;
Melonbooks included JPY 660 and an in-stock label; Mandarake returned a stock
confirmation label. Recommendations also occur in broad candidate selectors,
so these snapshots must not be directly applied as price updates.

To remove this feature, remove only its external extension registration and
native-host manifest, then restart Chrome; preserve profiles. Restore the
pre-DOM backend/controller/JS from `/var/backups/doujin-dom-*` if needed.
The paragraphs below retain the original comparison-mode implementation notes.

`native_server.py` launches the official Google Chrome stable package directly,
without Playwright attachment, CDP, WebDriver, automation flags, fingerprint
changes, or proxy rotation. The existing authenticated canvas relays the X11
desktop and human input using scrot/xdotool. It does not inspect DOM or cookies.
The existing start-source allowlist, session ownership, idle timeout, Unix socket,
service isolation and private-network restrictions remain. Chrome uses its normal
sandbox and Ubuntu's existing `/etc/apparmor.d/chrome` policy.

Stage `native_server.py`, `native.conf`, and `shop-browser.js`, then run
`install-native.sh` as root. The three shops use separate `native-*` profiles;
old Playwright profiles are not copied or deleted. A source switch closes the
previous Chrome instance. The displayed URL is the requested source, not a live
address bar. Reading prices is disabled for this manual comparison. It does not
prevent the human from following links or interacting with the shop itself.

On 2026-10-07 the native transport/lifecycle tests passed, and the live web viewer
showed Surugaya's age-confirmation page instead of the Cloudflare challenge for
the tested source. No CAPTCHA, age confirmation, or location grant was submitted.
This is not evidence that future requests or every product will pass.

To revert the service, remove only the `native.conf` systemd drop-in, daemon-reload
and restart `doujin-shop-browser`. The JS accepts either backend. Keep profiles
unless their deletion is explicitly requested. The following sections describe
the original Playwright reader backend, not the current native comparison.

The authenticated `/tools/shop-browser` page supports the three platforms already
recognized by `ShopProductReader`. It opens one selected source at a time on AWS.
Mouse, keyboard, and JPEG frames pass through PHP and a Unix socket. No VNC, CDP,
or new TCP control port is exposed. Users solve challenges themselves; the agent
does not click age confirmations or CAPTCHAs automatically.

`read` uses the current browser DOM and the existing product parser. It never
updates prices, availability, or books. A successful browser session is not a
promise that any shop will permit future requests or that all markup is parsed.

## Deployment

1. Stage the files in this folder under `/opt/doujin-shop-browser` as root-owned
   files. Run `install.sh` as root on Ubuntu 24.04. This installs a dedicated
   Python venv, Playwright's Chromium, Xvfb, Japanese fonts and a systemd service.
2. Deploy `ShopBrowser.php`, `sources/browser.php`, `shop-browser.js`, and
   `ShopProductReader.php` to their matching app/public paths.
3. For the previous cart version, check then apply `web-entry.patch` from the
   project root. The full repository Routes/view already include these entries;
   do not apply twice. No database migration or nginx change is required.
4. Open the tool as an authenticated administrator. Test each platform's frame,
   human input, read-only result and close controls.

The SSH helper requires an external credentials file and explicitly pinned host
fingerprint. Never commit credentials or persistent browser profiles.

## Isolation and lifecycle

- Dedicated non-login user; browser profiles live in a mode-0700 state directory.
- Chromium sandbox enabled; a narrowly attached AppArmor rule permits its user
  namespaces without turning off the global Ubuntu policy.
- Service cannot read the web project or home directories. Kernel cgroup IP
  rules block private, loopback and EC2 metadata destinations. Direct public DNS
  is bound only inside the service. Verify these rules on the target kernel.
- Pages are restricted to the chosen platform for top-level navigation; assets
  are public HTTP(S) only. Popups and downloads are disabled in this prototype.
- One active browser to limit memory (800MB hard cap); three separate persistent
  profiles. Only the owning PHP session can control the live browser.
- Fifteen minutes without human input/read closes Chromium. Frame polling alone
  does not extend this timeout. Closing a tab does not delete stored site cookies.
- No screenshot, HTML, credential, or input payload logging. Sessions are in
  memory, so restart invalidates control tokens while retaining platform cookies.

Stop/disable with `systemctl disable --now doujin-shop-browser`. Remove only the
tool routes/cart link to roll back the UI, restoring the pre-deployment backup.
Uninstalling the AppArmor profile/service does not require changing nginx or the
database. Delete profiles separately only with explicit authorization.
