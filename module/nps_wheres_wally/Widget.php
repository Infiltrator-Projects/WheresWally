<?php declare(strict_types = 1);

/**
 * WHERE'S WALLY — NPS Event Monitor
 *
 * Zabbix dashboard widget entry point.
 *
 * Copyright (C) 2026 Shannon Smith and Carlo Cunanan
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace Modules\NpsWheresWally;

use Zabbix\Core\CWidget;

/**
 * Registers the widget's default presentation metadata with Zabbix.
 *
 * The data acquisition and parsing responsibilities are intentionally kept out
 * of this class. Zabbix uses this object as the module-level widget entry
 * point; the controller and parser implement the operational behaviour.
 */
final class Widget extends CWidget {

    /**
     * Return the title used when a dashboard administrator does not provide a
     * custom widget name.
     */
    public function getDefaultName(): string {
        return _("WHERE'S WALLY — NPS Event Monitor");
    }
}
