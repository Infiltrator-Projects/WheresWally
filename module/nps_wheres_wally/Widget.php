<?php declare(strict_types = 1);

/**
 * WHERE'S WALLY — Zabbix dashboard widget entry point.
 *
 * Copyright (C) 2026 Shannon Smith and Carlo Cunanan
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace Modules\NpsWheresWally;

use Zabbix\Core\CWidget;

/**
 * Minimal module bootstrap required by Zabbix's widget loader.
 *
 * Runtime behaviour intentionally lives in the controller, form, parser and
 * client class. Keeping the bootstrap this small reduces framework coupling and
 * makes it obvious that no hidden side effects occur when the module is loaded.
 */
final class Widget extends CWidget {

    /** Return the translated name used when a dashboard has no custom label. */
    public function getDefaultName(): string {
        return _("WHERE'S WALLY — NPS Event Monitor");
    }
}
