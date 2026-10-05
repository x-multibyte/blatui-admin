<?php

declare(strict_types=1);

namespace BlatUI\Admin\Layout;

use BlatUI\Admin\Admin;
use BlatUI\Admin\Models\Administrator;
use BlatUI\Admin\Models\Menu as MenuModel;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\Support\Renderable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;

class Menu implements Htmlable, Renderable
{
    /**
     * View template.
     */
    protected string $view = 'blatui-admin::partials.sidebar-menu';

    /**
     * Build the hierarchical menu tree.
     *
     * @return array<int, array{
     *     id: int,
     *     parent_id: int,
     *     title: string,
     *     icon: ?string,
     *     uri: ?string,
     *     url: string,
     *     active: bool,
     *     children: array<int, mixed>
     * }>
     */
    public function toTree(): array
    {
        /** @var class-string<MenuModel> $menuClass */
        $menuClass = config('blatui-admin.database.menu_model', MenuModel::class);

        /** @var MenuModel $instance */
        $instance = new $menuClass;

        try {
            if (! Schema::hasTable($instance->getTable())) {
                return [];
            }

            /** @var Collection<int, MenuModel> $nodes */
            $nodes = $menuClass::query()
                ->where('show', 1)
                ->orderBy('order')
                ->get();
        } catch (Throwable) {
            return [];
        }

        /** @var Administrator|null $user */
        $user = Admin::user();

        // Filter authorized items
        $filtered = $nodes->filter(function (MenuModel $item) use ($user): bool {
            if ($user === null) {
                return false;
            }

            // Super admin has access to everything
            if ($user->isAdministrator()) {
                return true;
            }

            // If item has roles attached, check user role
            $itemRoles = $item->roles;

            if ($itemRoles->isNotEmpty()) {
                return $user->roles->pluck('id')->intersect($itemRoles->pluck('id'))->isNotEmpty();
            }

            return true;
        });

        return $this->buildTree($filtered->all(), 0);
    }

    /**
     * Recursively build tree nodes.
     *
     * @param  array<int, MenuModel>  $items
     * @return array<int, array{
     *     id: int,
     *     parent_id: int,
     *     title: string,
     *     icon: ?string,
     *     uri: ?string,
     *     url: string,
     *     active: bool,
     *     children: array<int, mixed>
     * }>
     */
    protected function buildTree(array $items, int $parentId = 0): array
    {
        $branch = [];

        foreach ($items as $item) {
            if ((int) $item->parent_id === $parentId) {
                $children = $this->buildTree($items, (int) $item->id);

                $url = $this->resolveUrl($item->uri);
                $isActive = $this->isActive($item->uri);

                // If any child is active, parent is also active
                $hasActiveChild = collect($children)->contains(fn (array $child): bool => (bool) $child['active']);

                if ($hasActiveChild) {
                    $isActive = true;
                }

                $branch[] = [
                    'id' => (int) $item->id,
                    'parent_id' => (int) $item->parent_id,
                    'title' => (string) $item->title,
                    'icon' => $item->icon ? (string) $item->icon : null,
                    'uri' => $item->uri ? (string) $item->uri : null,
                    'url' => $url,
                    'active' => $isActive,
                    'children' => $children,
                ];
            }
        }

        return $branch;
    }

    /**
     * Resolve url for a menu URI.
     */
    protected function resolveUrl(?string $uri): string
    {
        if (empty($uri)) {
            return '#';
        }

        if (str_starts_with($uri, 'http://') || str_starts_with($uri, 'https://')) {
            return $uri;
        }

        $prefix = (string) config('blatui-admin.route.prefix', 'admin');
        $cleanUri = ltrim($uri, '/');

        if ($cleanUri === '') {
            return '/'.$prefix;
        }

        return '/'.trim($prefix, '/').'/'.$cleanUri;
    }

    /**
     * Determine if given URI matches current request.
     */
    protected function isActive(?string $uri): bool
    {
        if (empty($uri)) {
            return false;
        }

        $prefix = (string) config('blatui-admin.route.prefix', 'admin');
        $cleanUri = ltrim($uri, '/');
        $path = $cleanUri === '' ? trim($prefix, '/') : trim($prefix, '/').'/'.$cleanUri;

        return request()->is($path) || request()->is($path.'/*');
    }

    /**
     * Set the view template.
     */
    public function view(string $view): static
    {
        $this->view = $view;

        return $this;
    }

    /**
     * Render the menu.
     */
    public function render(): string
    {
        if (view()->exists($this->view)) {
            return view($this->view, ['tree' => $this->toTree()])->render();
        }

        return '';
    }

    /**
     * Convert to HTML string.
     */
    public function toHtml(): string
    {
        return $this->render();
    }
}
