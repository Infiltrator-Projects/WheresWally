<?php declare(strict_types = 1);

/**
 * WHERE'S WALLY — dashboard editor view.
 *
 * Copyright (C) 2026 Shannon Smith and Carlo Cunanan
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * The editor deliberately delegates rendering to Zabbix's native field views so
 * validation messages, keyboard behaviour and visual styling remain consistent
 * with other dashboard widgets.
 *
 * @var CView $this
 * @var array<string, mixed> $data
 */

(new CWidgetFormView($data))
    ->addField(new CWidgetFieldMultiSelectItemView($data['fields']['itemid']))
    ->addField(new CWidgetFieldIntegerBoxView($data['fields']['show_lines']))
    ->show();
