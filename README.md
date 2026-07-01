# Felkyo Creatures

A cosy creature-collecting website. Players collect **creatures**, watch them
grow, pet them, explore, and spend a gentle in-game currency. It is built as a
learning project and as a clean foundation for a beginner to extend.

This repository is also teaching material: the code favours clarity over
cleverness, and everything is commented to explain *why*, not just *what*. The
coding rules live in [CLAUDE.md](CLAUDE.md); the build plan lives in
[felkyo-build-plan.md](felkyo-build-plan.md).

## Getting started

- **Run it on your machine:** [docs/setup-guide.md](docs/setup-guide.md)
- **Understand how it works:** [docs/developer-guide.md](docs/developer-guide.md)
- **The database:** [docs/schema.md](docs/schema.md)

## Tech stack

- PHP 8.2 (via XAMPP), MariaDB
- [Plates](https://platesphp.com/) templates, [Phinx](https://phinx.org/)
  migrations, [phpdotenv](https://github.com/vlucas/phpdotenv) for config
- [PHPUnit](https://phpunit.de/) for tests
- Vanilla JS + HTMX on the front end (no JS framework); CSS-first, mobile-first
