# CBS Radio platform: staged commit history

Twenty-five commits that record the stages the project went through, in the order they were built, in the
project's own style (Conventional Commits: `type: short description`, see CONTRIBUTING section 3).

`main` is the repository scaffold (commit 2). `develop` carries all 25 commits on top of it, as one linear history.

## The commits

### Before testing (1 to 20)

| # | Commit | Files | Lines |
|---|---|---|---|
| 1 | `docs: add the revised proposal and the signed-off six-week scope` | 2 | +0 |
| 2 | `chore: set up the repository, tooling and CI` | 14 | +1,323 |
| 3 | `feat: add the database schema` | 10 | +418 |
| 4 | `feat: add formatting and text helpers` | 3 | +383 |
| 5 | `feat: add the shared view cells` | 4 | +132 |
| 6 | `feat: add the models` | 11 | +1,039 |
| 7 | `feat: add the booking, scheduling, moderation, payment and gift services` | 7 | +1,099 |
| 8 | `feat: add message sanitising, file storage and the station configuration` | 5 | +756 |
| 9 | `feat: add the public, payment-callback and presenter controllers` | 8 | +644 |
| 10 | `feat: add category and show management` | 9 | +631 |
| 11 | `feat: add package (rate tier) management` | 3 | +277 |
| 12 | `feat: add gift vendors and vendor-sourced gifts` | 16 | +1,238 |
| 13 | `feat: add the public booking and gift views and the presenter screens` | 9 | +674 |
| 14 | `feat: add the admin dashboard, moderation, payments and notifications screens` | 7 | +749 |
| 15 | `feat: add the routes` | 1 | +162 |
| 16 | `chore: add a demo data seeder` | 1 | +196 |
| 17 | `feat: add booking search and the admin booking screens` | 5 | +555 |
| 18 | `feat: add admin-editable settings` | 17 | +1,615 |
| 19 | `feat: add the public homepage` | 12 | +1,179 |
| 20 | `feat: add the message editor and voice-note recorder` | 11 | +1,364 |

### The testing round (21 to 25)

| # | Commit | Files | Lines |
|---|---|---|---|
| 21 | `fix: correct the defects found while writing the PHPUnit suite` | 19 | +144 / -48 |
| 22 | `test: add the PHPUnit harness and the unit tests` | 41 | +2,888 |
| 23 | `test: add database tests for the models and services` | 20 | +4,053 |
| 24 | `test: add feature tests for the public site and the admin` | 11 | +3,204 |
| 25 | `chore: run the tests in CI and document them` | 7 | +87 / -16 |

Commit 21 is deliberately separate. It holds **every change to the application code that the PHPUnit suite forced**,
so the commits before it are the code as it stood before testing, and a reviewer can read exactly what testing
changed in one diff. Against the code at commit 20, 56 of the 894 tests fail; at commit 21 all of them pass.

## What to know about this history

- **It is reconstructed, not recorded.** The work was not committed as it happened, so this history was assembled
  afterwards. The order and the file sets of commits 2 to 15 follow the stage folders the project was delivered
  in; the later stages (settings, homepage, the editor, the tests) are grouped by what the files are.
- **Files are shown as they are today**, with four exceptions that accumulated features over time and so carry only
  what existed at each stage: the README and CONTRIBUTING (sections appear in the commit that introduces them), the
  routes file (each group of routes appears with its screen), the two Config classes (the settings hooks arrive with
  the settings), and `BookingController` (the example-message lookup arrives with the editor).
- **Early commits are not individually runnable.** A commit adds a coherent slice, but a slice may refer to a class
  that a later commit adds (the booking form view is in commit 13, its script and stylesheet in commit 20). Every
  commit's PHP parses, and commits 22 to 24 pass the tests they add, but do not `git bisect` through commits 3 to 20.
- **Dates and author.** Commit timestamps are when this history was built, not when the work happened; the real
  dates of the early stages are in the commit messages ("Stage built"). The author is a placeholder.
- **`docs/`** holds the two client documents (the proposal and the scope sign-off). It is in the history only; it
  is not part of the project zip.
- The final commit's tree is byte-for-byte the project in `cbs-radio-platform.zip` (212 files), apart from `docs/`.

## Using it

**A fresh repository** (the simplest):

```bash
git clone cbs-radio-platform.bundle cbs-radio-platform
cd cbs-radio-platform && git checkout develop
```

**On top of your existing repository** (the one `scripts/bootstrap-repo.sh` created, with the starter kit in it):

```bash
git checkout develop && git pull
git checkout -b chore/import-staged-history
git am --3way < cbs-radio-commits.mbox
```

If your repository already contains files from commit 2 (the README, `.gitignore`, `composer.json`), `git am`
stops on a conflict there. Resolve it, then `git add` and `git am --continue`, or `git am --skip` that one commit
if yours is the version you want to keep.

**Put your own name on the commits** (the author is a placeholder):

```bash
git rebase --root --exec 'git commit --amend --no-edit --reset-author'
```

(On an existing repository, replace `--root` with the commit before the first imported one.)

Push the branch and open a pull request into `develop` as usual; `develop` and `main` are protected.
