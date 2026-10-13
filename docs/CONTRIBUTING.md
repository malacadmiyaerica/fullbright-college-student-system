# Contributing

Thanks for contributing to the Fullbright College Student Management System.

## Getting started

1. **Clone** the repository:

   ```bash
   git clone https://github.com/malacadmiyaerica/fullbright-college-student-system.git
   ```

2. Follow the setup steps in the root [`README.md`](../README.md) (create the
   database, import `schema.sql`, configure `includes/config.php`).

## Branching

Create a branch for each change:

```bash
git checkout -b feature/short-description
```

Use a clear prefix such as `feature/`, `fix/`, or `docs/`.

## Commit messages

Use a short, descriptive summary. Examples:

```
Update: add same-as-permanent address checkbox
Fix: regenerate lost entrance exam on access
Docs: document email verification flow
```

## Pull requests

1. Push your branch: `git push -u origin feature/short-description`
2. Open a **Pull Request** against `main`.
3. Describe what changed and how you tested it.
4. Keep pull requests focused on a single concern.

All contributions are reviewed before merging.

## Issues & task tracking

- **GitHub Issues** — bug reports and feature requests.
- **GitHub Projects** — planning and task status.

When filing a bug, include steps to reproduce, expected vs. actual behavior,
and any relevant PHP/MySQL error output.

## Coding guidelines

- PHP: follow the existing style (PDO prepared statements, no inline SQL
  concatenation of user input, escape all output with `htmlspecialchars`).
- Never commit secrets, the `.env` file, uploaded student documents, or
  generated ID-card images (they are covered by `.gitignore`).
- Test enrollment, exam, login, and profile flows before opening a PR.

## Running locally

```bash
# from the project root
php -S localhost:8080
```

Then open <http://localhost:8080/>.
