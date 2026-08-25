<?php declare(strict_types = 1);

/**
 * WHERE'S WALLY — dashboard editor view.
 *
 * Copyright (C) 2026 Shannon Smith and Carlo Cunanan
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @var CView $this
 * @var array<string, mixed> $data
 */

(new CWidgetFormView($data))
    ->addField(new CWidgetFieldMultiSelectItemView($data['fields']['itemid']))
    ->addField(new CWidgetFieldIntegerBoxView($data['fields']['show_lines']))
    ->show();
