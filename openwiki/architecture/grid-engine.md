---
type: concept
title: Grid Builder, Repositories, and Data Presentation
description: Comprehensive architecture of the Grid data engine, Repository abstraction, Column displayers, query filtering, and interactive row/batch actions.
tags: [architecture, grid, repository, dsl, displayers, filters, table]
verified:
  - by: openwiki/0.6.0
    at: 2026-09-27T09:35:07.392Z
sources:
  - id: openwiki-source-2e7763f87182c4ee4e9b72fc
    resource: repo://resources/views/grid/table.blade.php
  - id: openwiki-source-04ff14adf6f9c5bb6a7457ec
    resource: repo://src/Contracts/Repository.php
  - id: openwiki-source-656c1a700c047fd26f423f88
    resource: repo://src/Grid.php
  - id: openwiki-source-8091c8e8db202bdf3e7a7606
    resource: repo://src/Grid/Column.php
  - id: openwiki-source-48030c6a96a84fadeb3181ff
    resource: repo://src/Grid/Filter.php
  - id: openwiki-source-b385a4e68f277121696b643d
    resource: repo://src/Grid/RowAction.php
  - id: openwiki-source-bdd7ff31cfb5748e16c0c776
    resource: repo://src/Repositories/EloquentRepository.php
generated: { by: "antigravity", at: "2026-09-27T09:35:07.392Z" }
---

# Grid Builder, Repositories, and Data Presentation

## Architectural Overview

The BlatUI Admin Grid engine provides a declarative, fluent PHP builder DSL for querying, presenting, filtering, and mutating relational tabular datasets. It decouples presentation logic from persistence via a dedicated Repository abstraction, formats columnar output through modular display pipelines, executes query filters against underlying Eloquent queries, and manages batch interactions via Alpine.js.

```
+-------------------------------------------------------------+
|                         Grid (DSL)                          |
+-------------------------------------------------------------+
        |                    |                    |
        v                    v                    v
+---------------+    +---------------+    +---------------+
|  Grid\Model   |    |    Columns    |    |  Grid\Filter  |
| (Repository)  |    | (Displayers)  |    | (Equal/Like)  |
+---------------+    +---------------+    +---------------+
        |                    |                    |
        +--------------------+--------------------+
                             |
                             v
                 +-----------------------+
                 |  resources/views/     |
                 |  grid/table.blade.php |
                 |  (Alpine.js + UI)     |
                 +-----------------------+
```

---

## Data Repository Abstraction

Persistence decoupling is guaranteed through the `BlatUI\Admin\Contracts\Repository` contract and its default implementation `BlatUI\Admin\Repositories\EloquentRepository`.

### The `Repository` Contract

The interface exposes standard data-source interrogation and mutation hooks:
- `getKeyName(): string`: Retrieves the entity's primary key identifier.
- `getCreatedAtColumn(): ?string` & `getUpdatedAtColumn(): ?string`: Obtains timestamp columns when supported.
- `isSoftDeletes(): bool`: Detects whether the repository source utilizes soft deletion.
- `model(): mixed`: Exposes the underlying query builder or Eloquent model.
- `edit(mixed $key): mixed`: Retrieves a specific model instance for form mutation.
- `update(mixed $key, array $values): bool`: Updates record attributes.
- `destroy(mixed $key): bool`: Removes one or multiple records by primary key.

### `EloquentRepository` Implementation

`EloquentRepository` accepts an Eloquent `Model`, query `Builder`, or class-string. It proxies pagination queries, handles soft-delete detection recursively via `class_uses_recursive`, and supports eager-loading relationships defined on the underlying builder.

---

## Fluent Grid DSL & Lifecycle

The `BlatUI\Admin\Grid` class acts as the central coordinator implementing `Htmlable`, `Renderable`, and `Responsable`.

### Core Lifecycle:
1. **Instantiation**: Constructed via `Grid::make($repository, Closure $callback)`.
2. **Column Registration**: Columns are declared via `$grid->column('attribute', 'Header Label')`.
3. **Data Fetching**: The `Grid\Model` coordinator executes repository queries, applying sorting parameters, filter constraints, and pagination limits (`paginate()`).
4. **Row Materialization**: Results are wrapped into `BlatUI\Admin\Grid\Row` instances exposing column values and row actions.
5. **View Rendering**: Outputs `blatui-admin::grid.table` with filter bars, tools, checkboxes, and pagination controls.

```php
return Grid::make(new User(), function (Grid $grid) {
    $grid->column('id', 'ID')->sortable();
    $grid->column('name', 'Name');
    $grid->column('status')->badge(['active' => 'success', 'disabled' => 'danger']);
    $grid->column('created_at')->datetime();

    $grid->filter(function (Grid\Filter $filter) {
        $filter->equal('id');
        $filter->like('name');
    });
});
```

---

## Column System & Modifiable Displayers

The `BlatUI\Admin\Grid\Column` class coordinates cell presentation pipelines. Multiple displayers can be chained sequentially:

| Displayer | Method Signature | Purpose |
| :--- | :--- | :--- |
| **`Badge`** | `badge(array $map = [], string $default = 'primary')` | Maps column scalar values to BlatUI `<x-ui.badge>` components. |
| **`Datetime`** | `datetime(string $format = 'Y-m-d H:i:s')` | Formats timestamps and Carbon instances. |
| **`Image`** | `image(?string $server = null, int $width = 32, int $height = 32)` | Renders avatar/image thumbnails with configurable dimensions. |
| **`Copyable`** | `copyable()` | Adds a client-side clipboard copy button with visual confirmation. |
| **`Link`** | `link(?string $href = null, string $target = '_blank')` | Wraps cell text in an anchor tag with URL interpolation. |
| **`Limit`** | `limit(int $limit = 100, string $end = '...')` | Truncates extended strings. |
| **`Using`** | `using(array $options, mixed $default = null)` | Translates raw enumerated values into human-readable labels. |

---

## Query Filtering Engine

The `BlatUI\Admin\Grid\Filter` subsystem parses incoming HTTP request parameters and injects conditions into the repository query builder:

- **Filter Field Classes**:
  - `Equal`: Direct equality comparison (`$query->where($column, $value)`).
  - `Like`: Pattern matching (`$query->where($column, 'like', "%{$value}%")`).
  - `Between`: Interval constraints for dates or numbers (`$query->whereBetween($column, [$start, $end])`).
  - `Gt` / `Gte`: Greater-than or greater-than-or-equal comparisons.
  - `Lt` / `Lte`: Less-than or less-than-or-equal comparisons.
  - `In`: Set inclusion matching (`$query->whereIn($column, $values)`).
  - `Where`: Custom closure-driven query constraints.

---

## Row & Batch Actions Architecture

### Row Actions (`BlatUI\Admin\Grid\RowAction`)
Individual row controls rendered in the final table column. Built-in actions include:
- `Edit`: Navigates to resource edit form.
- `Show`: Displays read-only resource detail view.
- `Delete`: Prompts for confirmation and issues asynchronous DELETE requests.
- `QuickEdit`: Modal-driven inline editing.

### Batch Operations (`BlatUI\Admin\Grid\BatchAction`)
Header tool dropdowns operating on multiple selected row IDs:
- Integrated with `BlatUI\Admin\Grid\Tools`.
- Default `BatchDelete` action triggers bulk removal of selected primary keys.

---

## Client-Side Interactivity & Blade Rendering

Rendering is performed by `resources/views/grid/table.blade.php`:
- **Alpine.js Reactive State**: Coordinates row selection through `x-data="{ selectedRows: [], selectAll: false, toggleAll() }"`.
- **Bidirectional Checkbox Watching**: `$watch('selectedRows')` ensures the `selectAll` header checkbox accurately reflects whether all rows on the active page are checked.
- **Dark Mode & Styling**: Uses Tailwind CSS v4 design tokens (`dark:bg-gray-900`, `dark:border-gray-800`), matching shadcn-style component aesthetics.
