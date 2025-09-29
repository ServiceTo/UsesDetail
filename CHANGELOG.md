# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.3] - 2025-09-29
### Fixed
- Fixed "Cannot access property starting with '\0'" error when merging objects with private/protected properties
- Added property name cleaning to handle null bytes from object-to-array casting

## [1.0.2] - 2025-07-14
### Changed
- Improve code quality and robustness
- Add missing Arrayable import
- Improve error handling for JSON operations with null checks
- Add type hints and return types to methods
- Simplify complex merge logic with helper method
- Update PHPUnit and Orchestra Testbench version constraints

## [1.0.0] - 2025-04-29
### Added
- Initial release
- Laravel trait for handling dynamic model attributes
- JSON detail column functionality
- Support for Laravel 9.x and 10.x 