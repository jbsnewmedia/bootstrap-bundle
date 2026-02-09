# Project Development Guide

This document provides essential information for AI and developers working on the BootstrapBundle project.

## Project Context
BootstrapBundle is a Symfony bundle for building administration interfaces. It features a plugin architecture, user/role management, and dynamic UI components.

## Development Commands
All commands should be executed within the Docker container.

### Testing
- **Run PHPUnit tests:**
  `docker exec bootstrap-bundle-web-1 composer test`
- **Goal:** Maintain 100% code coverage.
- **Strict Rule:** `@codeCoverageIgnore` must never be used. All code paths must be tested.

### Code Quality & Static Analysis
- **PHPStan (Static Analysis):**
  `docker exec bootstrap-bundle-web-1 composer bin-phpstan`
- **Easy Coding Standard (ECS) - Fix issues:**
  `docker exec bootstrap-bundle-web-1 composer bin-ecs-fix`
- **Rector (Automated Refactoring):**
  `docker exec bootstrap-bundle-web-1 composer bin-rector-process`

## Code Style & Comments
- **Minimal Commenting**: All comments `//` that are not strictly necessary for Code Quality (e.g., PHPStan types) must be removed.
- **No Unnecessary Explanations**: Code should be self-explanatory. DocBlocks that only repeat method names or trivial logic are forbidden.
- **Cleanup Command**: If comments have been added, they can be cleaned up using `composer bin-ecs-fix` (if configured) or manually.

## Project Structure Highlights
- `.developer/`: Additional development documentation.
- `.junie/`: AI-specific configuration and documentation.
- `tests/`: Comprehensive test suite for core and plugin functionalities.


