# Performance Testing Guide

This document explains how to write and run performance tests in Felkyo. The goal is to catch performance regressions *before* they reach production.

## Overview

We test two things together:
1. **Functionality**: Does the feature work correctly?
2. **Performance**: Does it run efficiently (not doing N+1 queries, using indexes, etc.)?

### The Three New Test Classes

#### 1. `PerformanceTestCase` (base class)
Location: `tests/PerformanceTestCase.php`

Extends `DatabaseTestCase` with performance tracking. Use this as the base for any test that needs to measure speed or query counts.

**Methods:**
- `startPerfMonitoring()` — Start measuring
- `stopPerfMonitoring()` — Stop measuring
- `assertMaxExecutionTime(int $ms, string $message)` — Assert code ran in under N milliseconds
- `assertQueryCount(int $expected)` — Assert exactly N queries ran (when logging is active)
- `assertMaxQueries(int $max)` — Assert at most N queries ran
- `assertIndexExists(string $table, string $column)` — Assert an index exists (prevents full-table scans)
- `getExecutionTimeMs()` — Get the elapsed time in milliseconds
- `getQueryCount()` — Get the number of queries that ran
- `printQueryReport()` — Print all queries (debugging)

#### 2. `PettingServicePerformanceTest`
Location: `tests/Integration/PettingServicePerformanceTest.php`

Performance tests for the core "petting a creature" feature. Tests:
- Single pet completes in < 100ms
- 50 consecutive pets complete in < 1 second
- 100 pets complete in < 2 seconds
- Database indexes are in place (no full-table scans)
- Cooldown checks are optimized

#### 3. `DatabasePerformanceTest`
Location: `tests/Integration/DatabasePerformanceTest.php`

Schema-level performance verification. Tests that all tables have the indexes they need:
- `users` table indexed on `username`, `email`
- `creatures` table indexed on `owner_id`
- `pettings` table indexed on `creature_id`, `actor_user_id` (critical for high-volume table)
- `inventory` table indexed on `user_id`
- And more...

If a test fails here, it means a query could do a full-table scan at scale.

---

## How to Write a Performance Test

### Basic Pattern

```php
class MyFeaturePerformanceTest extends PerformanceTestCase
{
    public function testMyFeatureIsEfficientUnderLoad()
    {
        // Setup: create test data
        $creature = $this->creatures->create($userId, $speciesId, 'TestCreature');
        
        // Monitor: start timing
        $this->startPerfMonitoring();
        
        // Act: run the code you're testing
        $result = $this->myService->doSomething($creature);
        
        // Measure: stop timing
        $this->stopPerfMonitoring();
        
        // Assert: check both functionality and performance
        $this->assertTrue($result->isSuccessful());
        $this->assertMaxExecutionTime(100, 'Should complete in under 100ms');
    }
}
```

### Testing Indexes

```php
public function testRequiredIndexesExist()
{
    // This will fail if the index doesn't exist (warning before production)
    $this->assertIndexExists('creatures', 'owner_id');
    $this->assertIndexExists('pettings', 'creature_id');
    $this->assertIndexExists('pettings', 'actor_user_id');
}
```

### Testing Under Load

```php
public function testManyCreaturesStayFast()
{
    // Create 100 users
    $users = [];
    for ($i = 0; $i < 100; $i++) {
        $users[] = $this->users->create("user_$i", "user_$i@example.com", 'hash');
    }
    
    // Time the operation with all 100 users
    $this->startPerfMonitoring();
    foreach ($users as $user) {
        $this->myService->doSomething($user);
    }
    $this->stopPerfMonitoring();
    
    // Should complete in reasonable time (not scale quadratically)
    $this->assertMaxExecutionTime(500); // 5ms per operation on average
}
```

---

## Running the Tests

From your IDE or command line:

```bash
# Run all performance tests
php vendor/bin/phpunit tests/Integration/*PerformanceTest.php

# Run a specific test class
php vendor/bin/phpunit tests/Integration/DatabasePerformanceTest.php

# Run a single test method
php vendor/bin/phpunit tests/Integration/PettingServicePerformanceTest.php::testLoadTest100Pets

# With verbose output (shows timings)
php vendor/bin/phpunit tests/Integration/PettingServicePerformanceTest.php --verbose
```

---

## Performance Benchmarks (Target Times)

Use these as baselines when writing assertions:

| Operation | Target Time | Notes |
|-----------|------------|-------|
| Single creature pet | < 100ms | Should be instant |
| 50 consecutive pets | < 1 second | Average 20ms per pet |
| 100 consecutive pets | < 2 seconds | Load test |
| Creature lookup by ID | < 10ms | With index |
| List user's creatures | < 50ms | Up to 10 creatures |
| Inventory lookup | < 20ms | With index |
| Cooldown check | < 10ms | Indexed query |

If a test is slower than this, investigate:
1. Is an index missing? (Check `DatabasePerformanceTest`)
2. Is there an N+1 query? (Should load data in 1-2 queries, not per-item)
3. Is the database query inefficient? (Use `EXPLAIN` to check)

---

## Interpreting Slow Tests

If a test fails (too slow):

### Step 1: Check Indexes
```bash
# In MySQL/MariaDB, see what indexes exist
SHOW INDEXES FROM creatures;
SHOW INDEXES FROM pettings;
```

Look for the table and column mentioned in the slow test.

### Step 2: Use EXPLAIN
```bash
# See how the database plans to execute a query
EXPLAIN SELECT * FROM pettings WHERE creature_id = 1 AND actor_user_id = 2;
```

If it says "Full Scan", an index is missing. If it says "Using Index", the index is helping.

### Step 3: Check for N+1 Queries
Use `printQueryReport()` in your test:

```php
public function testMyFeature()
{
    $this->startPerfMonitoring();
    // ... test code ...
    $this->stopPerfMonitoring();
    
    $this->printQueryReport(); // Shows all queries
}
```

If you see 100 queries for loading 100 items, there's an N+1 problem (should be 1-2 queries).

---

## Adding Performance Tests to Existing Tests

The goal is to wrap existing functionality tests with performance checks. For example, the original `PettingServiceTest` tests correctness. The new `PettingServicePerformanceTest` tests speed.

When you add a new feature:
1. Write functionality tests first (in the existing test class)
2. Separately, write performance tests (in a new `*PerformanceTest` class)
3. Both should pass before shipping

This keeps tests readable: tests that check "does this work?" separate from tests that check "does this work fast?"

---

## Future Improvements

### Query Logging
Currently, `PerformanceTestCase` is set up for query logging, but raw PDO doesn't log queries automatically. To add full query logging:

1. Wrap `PDOStatement::execute()` in tests
2. Or use a framework wrapper (like Laravel's DB::enableQueryLog())
3. Then `assertQueryCount()` will actually verify query counts

### Profiling Integration
Could integrate with Blackfire or XHProf for CPU flame graphs (not just timing).

### Baseline Regression Detection
Could store baseline performance numbers and auto-fail tests that regress by > 10%.

---

## Checklist: When Adding a New Feature

Before shipping a new feature, answer:
1. **Tests written?** Both functionality tests and performance tests?
2. **Indexes in place?** Did you add indexes for new WHERE clauses? Run `DatabasePerformanceTest`.
3. **N+1 free?** Are you loading data efficiently? (1-2 queries, not per-item)
4. **Speed acceptable?** Does it meet the benchmarks above?
5. **Scalable?** Could it handle 10x more data without breaking?

---

## Questions?

If a test is failing or confusing, ask:
- "What is this test checking?" (Look at the test name and comments)
- "Is this a real problem or a false positive?" (Try `printQueryReport()`)
- "Should this benchmark be tighter or looser?" (Adjust if hardware-dependent)

Performance testing is about *visibility*, not perfection. A slow test is often better than silent degradation.
