<?php declare(strict_types = 1);

/**
 * WHERE'S WALLY — widget configuration model.
 *
 * Copyright (C) 2026 Shannon Smith and Carlo Cunanan
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace Modules\NpsWheresWally\Includes;

use Zabbix\Widgets\CWidgetForm;
use Zabbix\Widgets\Fields\CWidgetFieldMultiSelectItem;
use Zabbix\Widgets\Fields\CWidgetFieldNumericBox;

/**
 * Defines the persistent configuration fields exposed by the dashboard editor.
 *
 * Design note:
 * The source item remains optional because the controller can locate the
 * canonical NPS log item automatically. Explicit selection is retained for
 * installations that use a different host name or item naming convention.
 */
final class WidgetForm extends CWidgetForm {

    public const DEFAULT_ROW_LIMIT = 200;

    /**
     * Add the widget-specific form fields to the Zabbix form model.
     */
    public function addFields(): self {
        return $this
            ->addField(
                (new CWidgetFieldMultiSelectItem(
                    'itemid',
                    _('NPS event-log item (automatic if blank)')
                ))->setMultiple(false)
            )
            ->addField(
                (new CWidgetFieldNumericBox('show_lines', _('Rows returned (10–200)')))
                    ->setDefault(self::DEFAULT_ROW_LIMIT)
            );
    }
}
