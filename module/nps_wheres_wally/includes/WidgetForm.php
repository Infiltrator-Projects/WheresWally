<?php declare(strict_types = 1);

/**
 * WHERE'S WALLY — widget configuration model.
 *
 * Copyright (C) 2026 Shannon Smith and Carlo Cunanan
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

namespace Modules\NpsWheresWally\Includes;

use Zabbix\Widgets\CWidgetForm;
use Zabbix\Widgets\Fields\CWidgetFieldIntegerBox;
use Zabbix\Widgets\Fields\CWidgetFieldMultiSelectItem;

/**
 * Define the small amount of state that belongs in the saved Zabbix dashboard.
 *
 * Search text, receipt dates, live/hold state and Clear state are intentionally
 * transient browser concerns and are therefore not persisted here. Only source
 * selection and the bounded row count survive dashboard reloads.
 */
final class WidgetForm extends CWidgetForm {

    public const MINIMUM_ROW_LIMIT = 10;
    public const MAXIMUM_ROW_LIMIT = 200;
    public const DEFAULT_ROW_LIMIT = 200;

    /** Register persistent widget fields using native Zabbix field types. */
    public function addFields(): self {
        return $this
            ->addField(
                (new CWidgetFieldMultiSelectItem(
                    'itemid',
                    _('NPS event-log item (automatic if blank)')
                ))->setMultiple(false)
            )
            ->addField(
                (new CWidgetFieldIntegerBox(
                    'show_lines',
                    _('Rows returned'),
                    self::MINIMUM_ROW_LIMIT,
                    self::MAXIMUM_ROW_LIMIT
                ))->setDefault(self::DEFAULT_ROW_LIMIT)
            );
    }
}
