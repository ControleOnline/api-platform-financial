<?php

declare(strict_types=1);

namespace DoctrineMigrations\Financial;

use ControleOnline\Migration\TenantAwareMigration;
use Doctrine\DBAL\Schema\Schema;

final class Version20261106120000 extends TenantAwareMigration
{
    public function getDescription(): string
    {
        return 'Seed the CashRegisterSummary (Caixa) manager home entry with link-type permissions.';
    }

    public function up(Schema $schema): void
    {
        $domain = $this->resolveExecutionDomain();
        $route = ['module_name' => 'ui-financial', 'route' => 'CashRegisterSummary', 'color' => '#0891B2', 'icon' => 'dollar-sign'];

        $this->addSql(
            'INSERT INTO routes (module_id, route, color, icon)
             SELECT module.id, :route, :color, :icon FROM module
             WHERE module.name = :module_name
               AND NOT EXISTS (SELECT 1 FROM routes WHERE route = :route)
             LIMIT 1',
            $route
        );
        $this->addSql(
            'UPDATE routes INNER JOIN module ON module.name = :module_name
             SET routes.module_id = module.id, routes.color = :color, routes.icon = :icon
             WHERE routes.route = :route',
            $route
        );

        $menu = [
            'domain' => $domain,
            'category_context' => 'menu',
            'category_name' => 'Financeiro',
            'route' => 'CashRegisterSummary',
            'menu' => 'Caixa',
            'menu_key' => 'cash_register_summary',
            'app_type' => 'MANAGER',
            'menu_type' => 'home',
            'route_params' => null,
            'sort_order' => 25,
            'enabled' => 1,
        ];

        $this->addSql(
            'INSERT INTO menu (category_id, menu, route_id, menu_key, app_type, menu_type, route_params, sort_order, enabled)
             SELECT category.id, :menu, routes.id, :menu_key, :app_type, :menu_type, :route_params, :sort_order, :enabled
             FROM category
             INNER JOIN people_domain ON people_domain.people_id = category.company_id
             INNER JOIN routes ON routes.route = :route
             WHERE people_domain.domain = :domain
               AND category.context = :category_context
               AND category.name = :category_name
               AND NOT EXISTS (
                   SELECT 1 FROM menu
                   WHERE app_type = :app_type AND menu_type = :menu_type AND menu_key = :menu_key
               )
             LIMIT 1',
            $menu
        );

        foreach (['owner', 'director', 'manager', 'salesman'] as $linkType) {
            $this->addSql(
                'INSERT INTO menu_link_type (menu_id, link_type)
                 SELECT menu.id, :link_type FROM menu
                 WHERE menu.app_type = :app_type AND menu.menu_type = :menu_type AND menu.menu_key = :menu_key
                   AND NOT EXISTS (
                       SELECT 1 FROM menu_link_type existing_link
                       WHERE existing_link.menu_id = menu.id AND existing_link.link_type = :link_type
                   )',
                ['link_type' => $linkType, 'app_type' => 'MANAGER', 'menu_type' => 'home', 'menu_key' => 'cash_register_summary']
            );
        }
    }

    public function down(Schema $schema): void
    {
    }
}
