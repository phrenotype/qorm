# PECULIAR IDs
**[ Table Of Contents](toc.md)**

Peculiar Identifiers are 64-bit sortable unique integers (similar to Twitter's Snowflake). They provide high performance in distributed systems and avoid the security risks of predictable auto-incrementing integers.

```php
'peculiar' => Field::Peculiar()
```

## Hybrid Generation System
QORM uses a robust hybrid system for ID generation:

1.  **APCu (Primary)**: Uses atomic APCu operations for maximum performance on modern servers.
2.  **File Fallback (Silent)**: Automatically falls back to a file-based lock mechanism (`flock`) on shared hosting or environments where APCu is unavailable.

### Project Isolation
By default, the file fallback uses a lock file isolated to your project root (via an MD5 hash of the directory path). This ensures different projects on the same server don't collide.

### Configuration
You can manually specify the lock file path in `qorm.config.php`:
```php
"Q_PECULIAR_LOCK_PATH" => "/home/user/my_app/peculiar.lock"
```

## Strict Enforcement
To ensure data integrity and prevent race conditions, Peculiar IDs are **system-managed**:
- **Automatic Generation**: IDs are automatically generated for every new record.
- **Manual Assignments Ignored**: Any attempt to manually set a value for a Peculiar field via the Model constructor, `create()`, or `update()` will be silently ignored and overwritten by the system.


---
**[Previous Part : Defaults](defaults.md)** | **[ Next Part : UUIDs ]( uuid.md )**
