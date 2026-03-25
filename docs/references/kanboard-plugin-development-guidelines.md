# Kanboard Plugin Development Guidelines

**Purpose:** This document captures hard-won lessons from building a Kanboard plugin from scratch. Every rule below corresponds to a real production bug encountered during development. It is written as an agent reference — a future AI coding agent starting a new Kanboard plugin project should read this before writing any code.

**Kanboard version:** >= 1.2.20 (these conventions may differ in older versions)

**Reference plugin:** [creecros/Customizer](https://github.com/creecros/Customizer) — a well-established community plugin that demonstrates correct patterns.

---

## Table of Contents

1. [Controller Architecture](#1-controller-architecture)
2. [URL Generation](#2-url-generation)
3. [Template Rendering](#3-template-rendering)
4. [Template Hooks & Widgets](#4-template-hooks--widgets)
5. [Asset Loading (CSS / JS)](#5-asset-loading-css--js)
6. [CSRF Protection](#6-csrf-protection)
7. [Request Data Access](#7-request-data-access)
8. [Translation / Localization](#8-translation--localization)
9. [DI Container & Service Access](#9-di-container--service-access)
10. [Model Layer & PicoDb](#10-model-layer--picodb)
11. [Automatic Actions](#11-automatic-actions)
12. [Task Search Filters](#12-task-search-filters)
13. [Page Layout & Theming](#13-page-layout--theming)
14. [Kanboard Model API Reference](#14-kanboard-model-api-reference)
15. [D3.js / Client-Side JavaScript](#15-d3js--client-side-javascript)
16. [Common Failure Patterns](#16-common-failure-patterns)

---

## 1. Controller Architecture

### Base Class

**Do:**
```php
use Kanboard\Controller\BaseController;

class MyController extends BaseController
```

**Don't:**
```php
use Kanboard\Core\Base;            // ✗ Missing controller methods
use Kanboard\Controller\Base;      // ✗ This class does not exist
```

**Why:** `Kanboard\Core\Base` is the DI container base class for models and helpers — it provides `__get()` magic for container access but lacks controller methods like `checkCSRFParam()`, `getProject()`, `getTask()`, etc. `Kanboard\Controller\Base` does not exist as a class. The correct parent is `Kanboard\Controller\BaseController`, which extends `Core\Base` and adds all controller infrastructure.

---

## 2. URL Generation

### Plugin Name in URL Parameters

**Do:**
```php
$this->helper->url->href('MyController', 'show', ['portfolio_id' => 1, 'plugin' => 'MyPlugin'])
```

**Don't:**
```php
$this->url->href('MyController', 'show', ['portfolio_id' => 1], 'MyPlugin')
```

**Why:** `UrlHelper::href()` has the signature `href($controller, $action, array $params = [], $csrf = false)`. The 4th parameter is a boolean `$csrf` flag, **not** the plugin name. Passing `'MyPlugin'` as the 4th argument silently sets `$csrf = true` while the plugin name is never included in the URL. Without `plugin=MyPlugin` in the query string, Kanboard routes to its core controllers.

**Symptoms of getting this wrong:**
- "Controller not found" — if there's no core controller with that name
- "Action not implemented" — if a core controller exists but lacks the method (e.g., core `ConfigController` exists but doesn't have your custom `show()` method)

### URL Helper Access in Controllers

**Do:**
```php
$this->helper->url->href(...)
```

**Don't:**
```php
$this->url->href(...)
```

**Why:** In controllers, `url` is not a direct Pimple container service. It's accessed through the helper registry at `$this->helper->url`. Using `$this->url` triggers `Identifier "url" is not defined`.

### URL Helper Access in Templates

In templates, `$this` is the template helper object, which has `$this->url` available directly:

```php
<!-- This works in templates (not controllers) -->
<?= $this->url->href('MyController', 'show', ['plugin' => 'MyPlugin']) ?>
```

---

## 3. Template Rendering

### Plugin Template Path Prefix

**Do:**
```php
$this->helper->layout->app('MyPlugin:path/template', $params);
```

**Don't:**
```php
$this->helper->layout->app('path/template', $params);
$this->template->render('path/template', $params);
```

**Why:** Without the `PluginName:` prefix, Kanboard's template resolver looks in `app/Template/` (core) instead of `plugins/PluginName/Template/`. This causes "Action not implemented" or blank pages with no obvious error.

### Layout Helpers vs Raw Render

**Do:**
```php
// Settings/config pages:
$this->response->html($this->helper->layout->config('MyPlugin:config/settings', $params));

// Regular application pages:
$this->response->html($this->helper->layout->app('MyPlugin:portfolio/show', $params));
```

**Don't:**
```php
$this->response->html($this->template->render('MyPlugin:portfolio/show', $params));
```

**Why:** `$this->template->render()` produces a bare HTML fragment without Kanboard's page shell — no header, navigation, sidebar, footer, or theme CSS. The layout helpers wrap your template content in the full Kanboard application layout, including theme support.

**Available layout helpers:**

| Helper | Use for |
|--------|---------|
| `$this->helper->layout->app(...)` | Regular plugin pages |
| `$this->helper->layout->config(...)` | Plugin settings pages (wraps in Settings layout with config sidebar) |
| `$this->helper->layout->project(...)` | Project-scoped pages |
| `$this->helper->layout->dashboard(...)` | Dashboard pages |

---

## 4. Template Hooks & Widgets

### Hook Registration: `attach()` vs `attachCallable()`

**Do** (when the widget template needs data from plugin services):
```php
$this->template->hook->attachCallable(
    'template:dashboard:show:before-task-list',
    'MyPlugin:widget/dashboard',
    function () {
        return [
            'portfolios' => $this->container['myModel']->getAll(),
        ];
    }
);
```

**Don't:**
```php
// Template will fail — plugin services aren't accessible via $this->xxx in templates
$this->template->hook->attach('template:dashboard:show:before-task-list', 'MyPlugin:widget/dashboard');
```
```php
<!-- This will throw "Identifier not defined" -->
<?php $data = $this->myPluginModel->getAll(); ?>
```

**Why:** Kanboard templates only expose core Kanboard services via `$this->xxx` (like `$this->text`, `$this->url`, `$this->form`, `$this->configModel`). Plugin-registered DI entries (e.g., `myPluginModel`, `myPluginHelper`) are NOT accessible in template context. `attachCallable()` runs the callback in the plugin context (where `$this->container[...]` works) and passes the returned array as template variables.

**Rule of thumb:**
- `attach()` — only for static templates that don't need plugin data (e.g., a sidebar link, a CSS include)
- `attachCallable()` — for any widget that needs data from plugin models or helpers

### Hook Registration for Assets

**Do:**
```php
// In Plugin::initialize() — registers through Kanboard's asset pipeline
$this->hook->on('template:layout:css', ['template' => 'plugins/MyPlugin/Asset/css/styles.css']);
```

**Don't:**
```php
// ✗ Wrong method — this passes a template reference string to AssetHelper::filemtime()
$this->template->hook->attach('template:layout:js', 'MyPlugin:widget/asset_js');

// ✗ Wrong — routes through a controller that doesn't exist
<script src="<?= $this->url->href('MyController', 'asset', ['file' => '...']) ?>">
```

**Why:** `template:layout:css` and `template:layout:js` are asset hooks processed by `AssetHelper`, which calls `filemtime()` on the path for cache-busting. It expects a real file path (e.g., `plugins/MyPlugin/Asset/css/styles.css`), not a template reference string.

---

## 5. Asset Loading (CSS / JS)

### In Templates (Per-Page Assets)

**Do:**
```php
<?= $this->asset->js('plugins/MyPlugin/Asset/js/my-script.js') ?>
<?= $this->asset->css('plugins/MyPlugin/Asset/css/my-style.css') ?>
```

**Don't:**
```php
<!-- ✗ No such controller/action exists -->
<script src="<?= $this->url->href('MyController', 'asset', ['file' => 'script.js']) ?>"></script>
```

**Why:** Kanboard serves plugin assets as static files directly from the filesystem. `$this->asset->js()` generates a `<script>` tag with the correct path and a `?timestamp` cache-buster via `filemtime()`. There is no built-in asset controller.

### Global vs Per-Page Loading

**Don't** register JS globally if it's only needed on one page:
```php
// This loads the script on EVERY page in Kanboard
$this->hook->on('template:layout:js', ['template' => 'plugins/MyPlugin/Asset/js/graph.js']);
```

If the same script is also loaded per-page via `$this->asset->js(...)`, it will execute twice — causing double rendering, double event listeners, etc.

**Do:** Register CSS globally (lightweight, needed on all pages). Load JS only on pages that need it via `$this->asset->js(...)` in the specific template.

### Bundle External Libraries

**Do:** Ship external JS libraries (e.g., D3.js) in `Asset/js/` and reference them with `$this->asset->js()`.

**Don't:** Use CDN references. Kanboard instances may be air-gapped or behind firewalls.

---

## 6. CSRF Protection

### The Two CSRF Methods

| Method | Reads from | Use for |
|--------|-----------|---------|
| `checkCSRFParam()` | `$_GET` (`getStringParam`) | GET-based destructive links (`<a href="?action=remove&csrf_token=xxx">`) |
| `checkCSRFForm()` | `$_POST` (`getRawValue`) | POST form submissions (if explicit validation is needed) |

### POST Form Convention

Kanboard core POST form handlers typically **do not call any CSRF check method**. The `$this->form->csrf()` hidden field is included in forms, and `$this->request->getValues()` already validates the CSRF token internally — it returns an **empty array** if the token is invalid.

**Do:**
```php
public function save()
{
    $values = $this->request->getValues();
    // $values is empty if CSRF failed — handle gracefully
    if ($this->myModel->create($values)) {
        $this->flash->success(t('Created successfully.'));
    }
    return $this->response->redirect(...);
}
```

**Don't:**
```php
public function save()
{
    $this->checkCSRFParam();  // ✗ Reads from $_GET — always fails for POST forms
    // ...
}
```

**Reference:** The [Customizer plugin](https://github.com/creecros/Customizer/blob/master/Controller/CustomizerConfigController.php) demonstrates this pattern — `save()` methods never call `checkCSRFParam()`.

---

## 7. Request Data Access

### GET vs POST Parameters

| Method | Source | Use for |
|--------|--------|---------|
| `$this->request->getIntegerParam('id')` | `$_GET` | URL query string params (route params) |
| `$this->request->getStringParam('name')` | `$_GET` | URL query string params |
| `$this->request->getValues()` | `$_POST` | Form body (also validates CSRF token) |
| `$this->request->getRawValue('field')` | `$_POST` | Single POST field without CSRF check |
| `$this->request->getValue('field')` | `$_POST` | Single POST field |

**Critical rule:** Form fields submitted via `method="post"` (like `project_id`, `task_id`, `position` in hidden inputs or selects) are in `$_POST`, NOT `$_GET`. Using `getIntegerParam()` to read them returns `0`.

**Do:**
```php
public function addProject()
{
    $portfolioId = $this->request->getIntegerParam('portfolio_id'); // from URL: ?portfolio_id=5
    $values = $this->request->getValues();                          // from POST body
    $projectId = (int) ($values['project_id'] ?? 0);
}
```

**Don't:**
```php
$projectId = $this->request->getIntegerParam('project_id'); // ✗ Returns 0 for POST fields
```

---

## 8. Translation / Localization

### Percent Signs in Translation Strings

**Do:**
```php
t('Threshold (%%)')    // Renders as "Threshold (%)"
```

**Don't:**
```php
t('Threshold (%)')     // ✗ Fatal: sprintf interprets % as format specifier
```

**Why:** Kanboard's `t()` function passes strings through `sprintf()`. A literal `%` is interpreted as a format specifier. Escape as `%%` both in the template and in `Locale/en_US/translations.php`.

---

## 9. DI Container & Service Access

### Where Services Are Accessible

| Context | Access pattern | Works for plugin services? |
|---------|---------------|---------------------------|
| Controllers (extending `BaseController`) | `$this->myModel` via `__get()` | ✅ Yes |
| Models (extending `Core\Base`) | `$this->otherModel` via `__get()` | ✅ Yes |
| Templates rendered by layout helpers | `$this->text`, `$this->url`, `$this->form`, `$this->asset` | ❌ Core only |
| Hook `attachCallable()` callbacks | `$this->container['myModel']` | ✅ Yes |

### Registering Plugin Helpers

If you register a helper in the DI container:
```php
$this->container['myHelper'] = function ($c) {
    return new MyHelper($c);
};
```

You can use `$this->myHelper` in controllers and models, but **NOT** in templates. For template data, pre-fetch in the controller or `attachCallable()` callback and pass as template variables.

---

## 10. Model Layer & PicoDb

### insert() Return Value

**Do:**
```php
$result = $this->db->table('my_table')->insert([...]);
return (bool) $result;  // true on success, false on failure
```

**Don't:**
```php
return is_int($result) && $result > 0;  // ✗ Always false — insert() returns bool, not int
```

**Why:** PicoDb's `Table::insert()` returns `true` on success and `false` on failure. It does NOT return a row count or last insert ID. To get the last insert ID, use `$this->db->getLastId()` after a successful insert.

### getValues() CSRF Side Effect

`$this->request->getValues()` internally validates the CSRF token and strips `csrf_token` from the returned array. If CSRF validation fails, it returns an **empty array**. This means:

1. You don't need to call `checkCSRFForm()` separately when using `getValues()`
2. You should handle the case where `getValues()` returns `[]`

---

## 11. Automatic Actions

### Required Methods on Action\Base

When creating automatic actions that extend `Kanboard\Action\Base`, you **must** implement these abstract methods:

```php
class MyAction extends Base
{
    public function getActionRequiredParameters(): array { return []; }
    public function getEventRequiredParameters(): array { return ['task_id']; }
    public function getCompatibleEvents(): array { return ['task.close']; }
    public function getDescription(): string { return t('My action'); }
    public function doAction(array $event): bool { /* ... */ }
    public function hasRequiredCondition(array $event): bool { return true; }
}
```

Missing `getActionRequiredParameters()` or `getEventRequiredParameters()` causes a fatal error on plugin load.

---

## 12. Task Search Filters

### Required Interface

Task search filters **must** extend `Kanboard\Filter\BaseFilter` and implement `Kanboard\Core\Filter\FilterInterface`.

**Do:**
```php
use Kanboard\Core\Filter\FilterInterface;
use Kanboard\Filter\BaseFilter;

class TaskMyFilter extends BaseFilter implements FilterInterface
{
    public function getAttributes() { return ['myfilter']; }
    public function apply() { /* modify $this->query */ return $this; }
}
```

**Don't:**
```php
use Kanboard\Core\Base;

class TaskMyFilter extends Base  // ✗ TypeError on every board page
```

**Why:** `LexerBuilder::withFilter()` type-checks its argument as `FilterInterface`. Using the wrong base class causes a `TypeError` that breaks **every board page** in the entire Kanboard instance — not just plugin pages.

### Filter Registration

**Do:**
```php
$container->extend('taskLexer', static function ($taskLexer, $c) {
    $filter = new TaskMyFilter();
    $filter->setContainer($c);  // For DI access inside the filter
    $taskLexer->withFilter($filter);
    return $taskLexer;
});
```

**Don't:**
```php
$taskLexer->withFilter(new TaskMyFilter($container));  // ✗ Constructor arg is $value, not container
```

**Why:** `BaseFilter::__construct($value)` takes the search value, not the container. Use a setter method for DI access.

---

## 13. Page Layout & Theming

### Sidebar Navigation

Kanboard uses this HTML structure for pages with sidebars:

```html
<section class="sidebar-container">
    <div class="sidebar">
        <h2>Section Title</h2>
        <ul>
            <li class="active"><a href="...">Current Page</a></li>
            <li><a href="...">Other Page</a></li>
        </ul>
    </div>
    <div class="sidebar-content">
        <!-- page content here -->
    </div>
</section>
```

The `class="active"` on `<li>` highlights the current page. Templates typically use a `$sidebar_active` variable to determine which item to highlight.

### Standard CSS Classes

Use Kanboard's built-in CSS classes for theme consistency:

| Element | Class |
|---------|-------|
| Page title area | `.page-header` |
| Buttons | `.btn`, `.btn-blue`, `.btn-red` |
| Tables | `.table-striped`, `.table-scrolling` |
| Alerts | `.alert`, `.alert-error` |
| Form actions | `.form-actions` |
| Content sections | `.listing` |
| Modals | `class="js-modal-large"` on `<a>` tags |

### Opening Task Detail Modals

To make links open the Kanboard task detail panel in a modal overlay:

```php
<a href="<?= $this->url->href('TaskViewController', 'show', ['task_id' => $id]) ?>"
   class="js-modal-large">
    #<?= $id ?>
</a>
```

The `js-modal-large` CSS class triggers Kanboard's built-in modal system.

### SVG Styles Don't Apply to HTML

SVG-specific CSS properties (`fill`, `stroke`) have no effect on HTML elements. If you reuse class names between SVG graph nodes and HTML legend swatches, you must provide separate CSS rules using `background`, `border`, `width`, `height` for the HTML versions.

---

## 14. Kanboard Model API Reference

These are the correct method names — getting them wrong causes fatal errors at runtime with no development-time warning.

| Model | Method | Returns | Notes |
|-------|--------|---------|-------|
| `userModel` | `getActiveUsersList($prepend = false)` | `[id => 'display name']` | For dropdowns. NOT `getList()` — that doesn't exist. |
| `userModel` | `getAll()` | `[['id' => ..., 'username' => ...], ...]` | Full user rows |
| `colorModel` | `getList($prepend = false)` | `['color_id' => 'Color Name']` | For dropdowns. This one IS `getList()`. |
| `projectModel` | `getAll()` | `[['id' => ..., 'name' => ...], ...]` | Full project rows |
| `configModel` | `get($key, $default)` | `mixed` | Read a setting |
| `configModel` | `save(array $values)` | `bool` | Save settings |

---

## 15. D3.js / Client-Side JavaScript

### D3 Drag vs Click Events

D3's drag behavior intercepts pointer events at every stage (`mousedown` → `mousemove` → `mouseup`), making click detection inside drag handlers unreliable. Threshold-based detection (measuring distance moved) fails because D3's force simulation causes micro-jitter in simulation coordinates even on clean clicks.

**Do:** Use a native DOM `click` listener on the container element. Walk up from `e.target` to find the nearest node group, then read the D3 datum:

```javascript
container.addEventListener('click', function (e) {
    var el = e.target;
    while (el && el !== container) {
        if (el.classList.contains('my-node-class')) {
            var datum = d3.select(el).datum();
            showPopover(datum, e);
            return;
        }
        el = el.parentElement;
    }
    hidePopover();
});
```

**Don't:** Try to detect clicks inside D3 drag event handlers — it will be unreliable regardless of threshold tuning.

---

## 16. Common Failure Patterns

Quick-reference table of symptoms and their Kanboard-specific root causes.

| Symptom | Root Cause |
|---------|-----------|
| `Fatal error: Class "Kanboard\Controller\Base" not found` | Class doesn't exist. Use `Kanboard\Controller\BaseController`. |
| `Fatal error: ... contains N abstract methods` on Action classes | Missing `getActionRequiredParameters()` and/or `getEventRequiredParameters()`. |
| `Warning: filemtime(): stat failed for ... Plugin:widget/asset_js` | Used `template->hook->attach()` for an asset hook. Use `$this->hook->on('template:layout:js', ['template' => 'plugins/...'])`. |
| `Internal Error: Identifier "X" is not defined` in templates | Template tries to access a plugin DI service via `$this->xxx`. Use `attachCallable()` to pre-fetch data. |
| `Internal Error: Identifier "url" is not defined` in controllers | Used `$this->url->...` instead of `$this->helper->url->...`. |
| `Internal Error: Action not implemented` | Template path missing `PluginName:` prefix, OR URL missing `'plugin' => 'PluginName'` in params (routing to core controller). |
| `Internal Error: Controller not found` | URL missing `'plugin' => 'PluginName'` in params. |
| `Internal Error: Access Forbidden` on POST | Called `checkCSRFParam()` which reads from `$_GET`. For POST forms, either use `checkCSRFForm()`, or rely on `getValues()` which validates CSRF internally. Best practice: don't call any CSRF method for POST handlers. |
| `ArgumentCountError: 2 arguments are required, 1 given` in Translator | Literal `%` in a `t()` string. Escape as `%%`. |
| `TypeError: ... must be of type FilterInterface` | Task filter doesn't extend `BaseFilter` / implement `FilterInterface`. Breaks ALL board pages. |
| `Call to undefined method UserModel::getList()` | Method doesn't exist. Use `getActiveUsersList()`. |
| Plugin pages render without header/nav/theme | Used `$this->template->render()` instead of `$this->helper->layout->app()`. |
| POST form values are all zero/empty | Used `getIntegerParam()` / `getStringParam()` (reads `$_GET`) for POST body fields. Use `getValues()` or `getRawValue()`. |
| PicoDb insert succeeds but result check fails | `insert()` returns `bool`, not `int`. Don't check `is_int($result)`. |
| Graph/script renders twice | Same JS file loaded both globally (via `hook->on`) and per-page (via `$this->asset->js()`). |
| `<script src>` returns HTML error page instead of JS | Used `url->href()` to route through a nonexistent asset controller. Use `$this->asset->js('plugins/...')`. |
| Form succeeds but shows failure flash message | PicoDb return value check is wrong (see "PicoDb insert" above). |
