# Contributing to Conduit PHP Framework

Thank you for considering contributing to Conduit PHP! We welcome contributions from the community.

## 🤝 How to Contribute

### Reporting Bugs

Before creating a bug report, please check existing issues to avoid duplicates.

**When filing a bug report, include:**
- PHP version
- Database type and version
- Steps to reproduce
- Expected vs actual behavior
- Error messages and stack traces
- Relevant code snippets

### Suggesting Features

We love new ideas! Feature requests are welcome.

**When suggesting a feature:**
- Explain the use case
- Describe the expected behavior
- Consider shared hosting compatibility
- Think about performance impact

### Pull Requests

1. **Fork the repository**
2. **Create a feature branch** (`git checkout -b feature/amazing-feature`)
3. **Make your changes**
4. **Write tests** for your changes
5. **Ensure tests pass** (`composer test`)
6. **Follow coding standards** (PSR-12)
7. **Commit your changes** (`git commit -m 'Add amazing feature'`)
8. **Push to branch** (`git push origin feature/amazing-feature`)
9. **Open a Pull Request**

## 📝 Coding Standards

### PSR-12 Compliance

Conduit follows PSR-12 coding standard. Key points:

```php
<?php

namespace Conduit\Example;

use Conduit\Core\Application;

class ExampleClass
{
    /**
     * Property declaration
     */
    private string $property;
    
    /**
     * Constructor
     */
    public function __construct(Application $app)
    {
        $this->property = 'value';
    }
    
    /**
     * Method with type hints and return type
     */
    public function exampleMethod(string $param): bool
    {
        // Method body
        return true;
    }
}
```

### Naming Conventions

- **Classes**: PascalCase (`UserController`, `PostService`)
- **Methods**: camelCase (`getUserById`, `createPost`)
- **Properties**: camelCase (`$userId`, `$postTitle`)
- **Constants**: UPPER_SNAKE_CASE (`MAX_ATTEMPTS`, `CACHE_TTL`)
- **Namespaces**: PascalCase, match directory structure

### Documentation

- Add PHPDoc blocks for all classes and methods
- Include parameter types and return types
- Explain complex logic with comments (Turkish comments allowed for clarity)

```php
/**
 * Kullanıcıyı ID'ye göre bulur
 * 
 * @param int $id Kullanıcı ID'si
 * @return User|null Kullanıcı nesnesi veya null
 * @throws UserNotFoundException Kullanıcı bulunamazsa
 */
public function findUserById(int $id): ?User
{
    // Implementation
}
```

## 🧪 Testing

### Writing Tests

All new features must include tests:

```php
<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Conduit\Core\Container;

class ContainerTest extends TestCase
{
    public function testContainerCanBindAndResolve()
    {
        $container = new Container();
        
        $container->bind('test', fn() => 'value');
        
        $this->assertEquals('value', $container->get('test'));
    }
}
```

### Running Tests

```bash
# Run all tests
composer test

# Run specific test file
vendor/bin/phpunit tests/Unit/ContainerTest.php

# Run with coverage
composer test-coverage
```

### Test Coverage

- Aim for 80%+ code coverage
- All critical paths must be tested
- Edge cases should be covered

## 🔒 Security

If you discover a security vulnerability, please email security@conduitphp.com instead of using the issue tracker.

## 📋 Commit Messages

Write clear, descriptive commit messages:

```
Add user authentication feature

- Implement JWT authentication
- Add login and register endpoints
- Add authentication middleware
- Add tests for auth flow
```

### Commit Message Format

```
<type>: <subject>

<body>

<footer>
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes (formatting)
- `refactor`: Code refactoring
- `test`: Adding or updating tests
- `chore`: Maintenance tasks

## 🎯 Development Workflow

1. **Create an issue** describing what you plan to work on
2. **Wait for approval** from maintainers
3. **Fork and create branch** from `develop`
4. **Make changes** following guidelines
5. **Write tests** ensuring coverage
6. **Run test suite** locally
7. **Submit PR** with clear description
8. **Address review comments**
9. **Merge** once approved

## 🌍 Turkish Contributors

Türkçe konuşan katkıcılarımızı memnuniyetle karşılıyoruz!

**Türkçe Açıklamalar:**
- Kod içi yorumlar Türkçe olabilir
- PHPDoc'lar tercihen İngilizce
- Commit mesajları İngilizce
- Issue ve PR başlıkları İngilizce
- Tartışmalarda her iki dil de kullanılabilir

## 📚 Resources

- [Documentation](https://docs.conduitphp.com)
- [PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)
- [Semantic Versioning](https://semver.org/)

## ✅ Checklist

Before submitting a PR, ensure:

- [ ] Code follows PSR-12
- [ ] All tests pass
- [ ] New features have tests
- [ ] Documentation updated
- [ ] No merge conflicts
- [ ] Commit messages are clear
- [ ] PR description explains changes

## 🙏 Thank You!

Your contributions make Conduit PHP better. We appreciate your time and effort!

---

**Questions?** Feel free to ask in [GitHub Discussions](https://github.com/yourusername/conduit-framework/discussions)
