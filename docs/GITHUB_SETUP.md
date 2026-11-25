# Git and GitHub submission guide

No remote has been created or pushed from the authoring environment. Create your own repository, with visibility allowed by the recruitment instructions. Prefer a private repo with explicit evaluator access if possible; confirm with HR before publishing assignment materials. Do not publish the company's original PDF without permission; this archive deliberately does not copy it.

From extracted project root, after successful runtime QA and lockfile generation:

```bash
git init -b main
git status --short --ignored
git add .
git diff --cached --stat
git diff --cached --name-only
git diff --cached --check
git commit -m "feat: integrated outpatient SIMRS source release"
git remote add origin https://github.com/<YOUR-ACCOUNT>/<YOUR-REPOSITORY>.git
git push -u origin main
```

Replace placeholders with your actual URL. If a repo already exists, use `git status` and existing remote rather than reinitializing or overwriting history. Never force-push blindly. `.gitignore` excludes local secrets, dependencies, runtime output and QA logs; inspect staged files and contents manually. Ensure no `.env`, auth tokens, credentials, actual patient data, private recruitment material, node_modules/vendor, or raw logs. Commit actual lockfiles and selected scrubbed evidence. Do not invent previous phase commits or dates. GitHub access requires your account and authorization; a local commit is not remote availability.

After verified changes, tag only the actual tested commit:

```bash
git tag -a v1.0.0 -m "SIMRS outpatient source release"
git push origin v1.0.0
```

Verify from a clean clone (or another account with evaluator permissions) that source, README, migration, Postman and evidence links open. For private repo invite authorized reviewer(s); ensure they can accept invite. Share URL and commit hash in submission email. Keep repository available during evaluation. No deployment is required by the original assignment.
