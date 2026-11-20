# Git Updater Install Stats

An add-on for [Git Updater](https://github.com/afragen/git-updater) by Andy Fragen. It requires Git Updater to be installed and active on your update server.

When your site serves updates to [Git Updater Lite](https://github.com/afragen/git-updater-lite) clients, this add-on loosely counts how many sites check for each plugin or theme, and shows the active installs in a dashboard widget.

## How it works

- Only successful requests to `git-updater/v1/update-api` are counted. Error responses, such as unknown or unshared slugs, are ignored.
- Each client site is identified by the URL WordPress puts in its User-Agent (`WordPress/6.8; https://example.com`), falling back to the IP address. Only a salted hash is stored, never the URL or IP.
- A site counts as an active install if it checked within the last 30 days. Older records are pruned daily.
- Once a day, each slug's active install count is saved as a snapshot (kept for 90 days, in a site option). The widget draws these as a small trend line next to each count; hover it for the date range and the lowest and highest counts. History starts when the add-on is activated, so a trend appears after two days.
- The widget shows on the dashboard for administrators, and on multisite on the main site and Network Admin dashboards.

Counts are approximate: several sites behind one IP without a User-Agent URL count as one, and anything sending a fake WordPress User-Agent can inflate them.

## Requirements

- WordPress 6.5+
- PHP 8.0+
- [Git Updater](https://github.com/afragen/git-updater)

## License

GPL-3.0-or-later
