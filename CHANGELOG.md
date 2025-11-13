# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.6] - 2025-11-13
### Added
- Comprehensive test suite for pivot table queries with 8 new test cases
- Test models demonstrating relationships with both UsesDetail and non-UsesDetail models
- Tests covering mixed relationships (Category with UsesDetail ↔ Product without UsesDetail)
- Tests for relationships where both models use UsesDetail (Post ↔ Tag)
- Tests validating pivot table column queries (wherePivot, orderBy on pivot columns)
- Tests ensuring qualified column name resolution works correctly for both model and pivot tables

## [1.1.5] - 2025-11-13
### Fixed
- Fixed pivot table queries incorrectly resolving to detail JSON columns
- DetailQueryBuilder now properly handles qualified column names from other tables (e.g., pivot tables in relationships)
- When a qualified column name (table.column) references a table other than the model's table, it is now passed through without detail column resolution
- Fixes belongsToMany relationships where pivot table columns were incorrectly queried as JSON paths

## [1.1.4] - 2025-11-10
### Added
- Support for `groupBy()` with intelligent column routing for both schema and detail columns
- Support for `having()` and `orHaving()` clauses with detail columns
- Ability to group by multiple columns (mixing schema and detail columns)
- Comprehensive tests for groupBy functionality with 5 new test cases
- Documentation examples for groupBy and having usage

## [1.1.2] - 2025-10-21
### Added
- Support for `orderBy()` and `orderByDesc()` with intelligent column routing
- Support for `latest()` and `oldest()` helper methods
- Order by works seamlessly on both schema and detail columns

## [1.1.1] - 2025-10-21
### Added
- `MissingDetailColumnException` now throws when a model uses the trait but the table lacks a 'detail' column
- Helpful error message with migration hint when detail column is missing

### Fixed
- Better error handling and validation to prevent silent failures when detail column doesn't exist

## [1.1.0] - 2025-10-21
### Added
- Custom `DetailQueryBuilder` that intelligently routes queries to schema or JSON detail columns
- Support for all Laravel where methods: `where`, `orWhere`, `whereIn`, `whereNotIn`, `whereNull`, `whereNotNull`, `whereBetween`, `whereNotBetween` and their variants
- Automatic column detection with 5-minute schema caching
- Ability to use standard Laravel query methods on both schema and detail columns without distinction

### Changed
- `scopeDetail()` now delegates to the custom query builder for consistent behavior
- Improved performance by caching schema information for 5 minutes

### Technical
- Added `newEloquentBuilder()` method to use custom query builder
- Enhanced `where()` to handle qualified column names (table.column)
- All existing functionality remains backward compatible

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