# Deployment workflow

1. Make changes and run focused tests in the local repository.
2. Review the diff and encoding; exclude credentials, catalog databases, uploads,
   browser profiles, test output, and unrelated projects.
3. Commit all completed application changes and push the `dev` branch to GitHub.
4. Back up production and check its Git status before deployment. Resolve any
   existing drift explicitly; never discard production edits with a hard reset.
5. Pull the verified commit into production using a fast-forward-only update.
6. Run any required migrations and verify the affected authenticated pages.

Report the commit hash and distinguish GitHub publication from live deployment.
Direct file-copy deployment is not the default workflow. Historical scripts in
`tools/shop-browser` document earlier one-off operations; do not run them as a
routine deployment pipeline.

The November 2026 reconciliation commits capture local C109, browser-tool, cart,
and circle-book work. Production was observed on commit `93eadad` with uncommitted
files, so a plain pull must not be attempted before reconciling those files.
