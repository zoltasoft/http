# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/)
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [Unreleased]

---

## [2.0.0] - 2026-08-02

### Added
- Added `trustedData()` for server-derived actor, tenant, and route values during DTO mapping
- Added regression coverage for trusted-value precedence and server-only query context

### Changed
- Deprecated `withData()` while retaining it as a backwards-compatible trusted-data bridge
- Query context is now accepted only from server configuration, not HTTP query input

### Fixed
- Corrected the OpenAPI response-map type contract for numeric HTTP status keys
- Preserved Symfony HTTP exception status codes and headers in attribute-route responses

### Security
- Server-derived request values now override client-controlled values with the same key

---

## Version comparison links

[Unreleased]: https://github.com/zoltasoft/http/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/zoltasoft/http/compare/v1.0.0...v2.0.0
