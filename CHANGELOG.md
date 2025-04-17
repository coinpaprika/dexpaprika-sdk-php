# Changelog
All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2023-06-20

### Added
- Added missing exception classes:
  - ValidationException
  - AuthenticationException
  - RateLimitException
  - ServerException
  - ClientException
- Retry with backoff functionality in BaseApi
- Configurable retry options in Config class
- PSR-6 compatible caching system
  - CacheInterface for implementing custom caches
  - FilesystemCache implementation 
  - Cache support in Config and BaseApi
  - Convenient setupCache method in Client class

### Enhanced
- DexPaprikaApiException now includes error data from API responses
- Fixed NotFoundException to use the correct namespace and include error data
- Improved exception handling with detailed error information 