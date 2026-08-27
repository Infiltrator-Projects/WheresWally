<?php declare(strict_types = 1);

/**
 * WHERE'S WALLY — NPS event-table presentation.
 *
 * Copyright (C) 2026 Shannon Smith and Carlo Cunanan
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Presentation contract
 * ---------------------
 * The controller supplies already-parsed, already-authorised rows. This view is
 * responsible only for semantic markup and initial server-side labels. Transient
 * LIVE/HOLD state, retained-history criteria and Clear state are restored by the
 * client class after Zabbix replaces the widget body.
 *
 * @var CView $this
 * @var array<string, mixed> $data
 */

// Fail closed with a single accessible message instead of rendering a partially
// usable toolbar when the configured/automatic source item cannot be resolved.
if ($data['error'] !== null) {
    (new CWidgetView($data))
        ->addItem(
            (new CDiv((string) $data['error']))
                ->addClass('nps-wally-error')
                ->setAttribute('role', 'alert')
        )
        ->show();

    return;
}

/* -------------------------------------------------------------------------
 * Search and operator controls.
 * ---------------------------------------------------------------------- */

$filter = (new CTag('input', false))
    ->setAttribute('type', 'search')
    ->setAttribute('placeholder', _('Search retained history'))
    ->setAttribute('aria-label', _('Search retained NPS history'))
    ->setAttribute('autocomplete', 'off')
    ->setAttribute('maxlength', (string) $data['maximum_search_length'])
    ->addClass('nps-wally-filter');

$search_button = (new CTag('button', true, _('Search')))
    ->setAttribute('type', 'button')
    ->setAttribute('title', _('Run retained-history search using the current text and receipt dates'))
    ->addClass('nps-wally-button')
    ->addClass('nps-wally-button-primary')
    ->addClass('nps-wally-search-button');

$date_from = (new CTag('input', false))
    ->setAttribute('type', 'date')
    ->setAttribute('aria-label', _('Received from date'))
    ->setAttribute(
        'title',
        _('Optional Zabbix receipt-date start for retained-history search')
    )
    ->addClass('nps-wally-date')
    ->addClass('nps-wally-date-from');

$date_to = (new CTag('input', false))
    ->setAttribute('type', 'date')
    ->setAttribute('aria-label', _('Received to date'))
    ->setAttribute(
        'title',
        _('Optional Zabbix receipt-date end for retained-history search')
    )
    ->addClass('nps-wally-date')
    ->addClass('nps-wally-date-to');

$reset_search_button = (new CTag('button', true, _('Reset')))
    ->setAttribute('type', 'button')
    ->setAttribute('title', _('Clear search criteria and return to the live/hold view'))
    ->addClass('nps-wally-button')
    ->addClass('nps-wally-search-reset');

$export_button = (new CTag('button', true, '↧ '._('Export')))
    ->setAttribute('type', 'button')
    ->setAttribute('title', _('Export currently visible rows as spreadsheet-safe CSV'))
    ->addClass('nps-wally-button')
    ->addClass('nps-wally-export');

$clear_button = (new CTag('button', true, '× '._('Clear view')))
    ->setAttribute('type', 'button')
    ->setAttribute('title', _('Hide events currently displayed; Zabbix monitoring history is not deleted'))
    ->addClass('nps-wally-button')
    ->addClass('nps-wally-clear');

// Auto-scroll is intentionally presented as a switch because it changes the
// operational mode of the console: ON = LIVE, OFF = HOLD.
$auto_scroll = (new CTag('input', false))
    ->setAttribute('type', 'checkbox')
    ->setAttribute('checked', 'checked')
    ->setAttribute('role', 'switch')
    ->setAttribute('aria-label', _('Auto-scroll live feed'))
    ->setAttribute('title', _('On: one-second live updates; Off: hold the current event list'))
    ->addClass('nps-wally-autoscroll');

$auto_scroll_label = (new CTag('label', true, [
    $auto_scroll,
    (new CSpan())->addClass('nps-wally-switch-track')->setAttribute('aria-hidden', 'true'),
    (new CSpan(_('Auto-scroll')))->addClass('nps-wally-switch-label')
]))
    ->addClass('nps-wally-auto-label')
    ->setAttribute('title', _('Auto-scroll controls LIVE versus HOLD'));

$mode = (new CSpan($data['query_active'] ? _('SEARCH') : _('LIVE')))
    ->addClass('nps-wally-mode')
    ->addClass($data['query_active'] ? 'is-search' : 'is-live')
    ->setAttribute('role', 'status')
    ->setAttribute('aria-live', 'polite')
    ->setAttribute('data-live-label', _('LIVE'))
    ->setAttribute('data-hold-label', _('HOLD'))
    ->setAttribute('data-search-label', _('SEARCH'))
    ->setAttribute(
        'data-live-title',
        _('Auto-scroll is on: checking for new NPS events every second')
    )
    ->setAttribute(
        'data-hold-title',
        _('Auto-scroll is off: the current event list is held in place')
    )
    ->setAttribute(
        'data-search-title',
        _('Server-side retained-history search; use Search or Enter to refresh results')
    );

$toolbar_header = (new CDiv([
    (new CDiv([
        (new CSpan("WHERE'S WALLY"))->addClass('nps-wally-title'),
        (new CSpan(_('NPS Event Monitor · 6272 Grant · 6273 Deny')))
            ->addClass('nps-wally-subtitle')
    ]))->addClass('nps-wally-brand'),
    (new CDiv([$auto_scroll_label, $mode]))->addClass('nps-wally-mode-cluster')
]))->addClass('nps-wally-toolbar-header');

$search_group = (new CDiv([$filter, $search_button]))
    ->addClass('nps-wally-search-group');

$date_group = (new CDiv([
    (new CTag('label', true, [
        (new CSpan(_('Received from')))->addClass('nps-wally-field-label'),
        $date_from
    ]))->addClass('nps-wally-date-label'),
    (new CTag('label', true, [
        (new CSpan(_('Received to')))->addClass('nps-wally-field-label'),
        $date_to
    ]))->addClass('nps-wally-date-label')
]))->addClass('nps-wally-date-group');

$action_group = (new CDiv([
    $reset_search_button,
    $export_button,
    $clear_button
]))->addClass('nps-wally-action-group');

$toolbar_controls = (new CDiv([
    $search_group,
    $date_group,
    $action_group
]))->addClass('nps-wally-controls');

$toolbar = (new CDiv([$toolbar_header, $toolbar_controls]))
    ->addClass('nps-wally-toolbar');

/* -------------------------------------------------------------------------
 * Event table.
 *
 * Event and detail rows are emitted as adjacent pairs. The JavaScript Clear and
 * Details logic relies on that adjacency, and the zebra-striping CSS accounts
 * for the hidden detail row occupying every second table-child position.
 * ---------------------------------------------------------------------- */

$headers = [
    ['label' => _('Event'), 'export' => true],
    ['label' => _('Time'), 'export' => true],
    ['label' => _('Account'), 'export' => true],
    ['label' => _('Domain'), 'export' => true],
    ['label' => _('Name'), 'export' => true],
    ['label' => _('Access point'), 'export' => true],
    ['label' => _('Location'), 'export' => true],
    ['label' => _('Device MAC'), 'export' => true],
    ['label' => _('IP address'), 'export' => true],
    ['label' => _('Result'), 'export' => true],
    ['label' => _('Details'), 'export' => false]
];

$header_cells = [];
foreach ($headers as $header) {
    $cell = (new CTag('th', true, $header['label']))
        ->setAttribute('scope', 'col');

    if ($header['export']) {
        // CSV headings are read from these translated cells by JavaScript so
        // export labels cannot drift away from the visible table.
        $cell->setAttribute('data-export-heading', '1');
    }

    $header_cells[] = $cell;
}

$body_rows = [];
$grant_count = 0;
$deny_count = 0;

foreach ($data['rows'] as $row) {
    $is_grant = $row['status'] === 'Grant';

    if ($is_grant) {
        $grant_count++;
    }
    else {
        $deny_count++;
    }

    $result = (new CSpan((string) $row['status_long']))
        ->addClass('nps-wally-result')
        ->addClass($is_grant ? 'is-grant' : 'is-deny')
        ->setAttribute('title', (string) $row['reason']);

    $detail_id = 'nps-wally-detail-'.(string) $row['received_key'];
    $details_button = (new CTag('button', true, _('Details')))
        ->setAttribute('type', 'button')
        ->setAttribute('aria-expanded', 'false')
        ->setAttribute('aria-controls', $detail_id)
        ->setAttribute('data-show-label', _('Details'))
        ->setAttribute('data-hide-label', _('Hide'))
        ->setAttribute(
            'title',
            _('Show parsed authentication metadata and the complete Windows event message')
        )
        ->addClass('nps-wally-details-button');

    $primary_cells = [
        ['value' => (string) $row['event_id'], 'class' => 'event-id'],
        ['value' => (string) $row['time'], 'class' => 'event-time'],
        ['value' => (string) $row['account'], 'class' => 'event-account'],
        ['value' => (string) $row['domain'], 'class' => 'event-domain'],
        ['value' => (string) $row['person'], 'class' => 'event-person'],
        ['value' => (string) $row['access_point'], 'class' => 'event-ap'],
        ['value' => (string) $row['location'], 'class' => 'event-location'],
        ['value' => (string) $row['device_mac'], 'class' => 'event-device'],
        ['value' => (string) $row['ip_address'], 'class' => 'event-ip']
    ];

    $table_cells = [];
    foreach ($primary_cells as $cell) {
        $table_cells[] = (new CTag('td', true, $cell['value']))
            ->addClass($cell['class'])
            ->setAttribute('data-export', '1')
            ->setAttribute('title', $cell['value']);
    }

    $table_cells[] = (new CTag('td', true, $result))
        ->addClass('event-result')
        ->setAttribute('data-export', '1');
    $table_cells[] = (new CTag('td', true, $details_button))
        ->addClass('event-details');

    $event_row = (new CTag('tr', true, $table_cells))
        ->addClass('nps-wally-event-row')
        ->addClass($is_grant ? 'is-grant' : 'is-deny')
        ->setAttribute('data-received-key', (string) $row['received_key'])
        ->setAttribute('data-result', (string) $row['status']);

    // Keep details concise by omitting unavailable fields rather than displaying
    // a row of empty "Label:" pills. Receipt time is always retained because it
    // explains date-filter behaviour independently of the Windows source time.
    $summary_items = [
        (new CSpan(_('Received').': '.(string) $row['received_time']))
            ->addClass('nps-detail-pill')
    ];

    $optional_details = [
        _('SSID') => (string) $row['ssid'],
        _('Network policy') => (string) $row['network_policy'],
        _('Connection policy') => (string) $row['connection_request_policy'],
        _('Authentication') => (string) $row['authentication_type'],
        _('EAP') => (string) $row['eap_type']
    ];

    foreach ($optional_details as $label => $value) {
        if ($value !== '') {
            $summary_items[] = (new CSpan($label.': '.$value))
                ->addClass('nps-detail-pill');
        }
    }

    if ($row['reason_code'] !== '') {
        $summary_items[] = (new CSpan(_('Reason code').': '.(string) $row['reason_code']))
            ->addClass('nps-detail-pill')
            ->addClass('is-deny');
    }

    if ($row['reason'] !== '') {
        $summary_items[] = (new CSpan((string) $row['reason']))
            ->addClass('nps-detail-reason');
    }

    $detail_content = (new CDiv([
        (new CDiv($summary_items))->addClass('nps-wally-detail-summary'),
        (new CTag('pre', true, (string) $row['message']))->addClass('nps-wally-message')
    ]))->addClass('nps-wally-detail-content');

    $detail_row = (new CTag(
        'tr',
        true,
        (new CTag('td', true, $detail_content))
            ->setAttribute('colspan', (string) count($headers))
    ))
        ->setAttribute('id', $detail_id)
        ->addClass('nps-wally-detail-row')
        ->setAttribute('hidden', 'hidden');

    $body_rows[] = $event_row;
    $body_rows[] = $detail_row;
}

if ($data['rows'] === []) {
    $empty_text = $data['query_active']
        ? _('No matching 6272 or 6273 events were found in retained history.')
        : _('No 6272 or 6273 events are available yet.');

    $body_rows[] = new CTag(
        'tr',
        true,
        (new CTag('td', true, $empty_text))
            ->setAttribute('colspan', (string) count($headers))
            ->addClass('nps-wally-empty')
    );
}

$table = (new CTag('table', true, [
    new CTag('thead', true, new CTag('tr', true, $header_cells)),
    new CTag('tbody', true, $body_rows)
]))
    ->addClass('nps-wally-table')
    ->setAttribute('aria-label', _('NPS authentication events'));

$scroller = (new CDiv($table))
    ->addClass('nps-wally-scroller')
    ->setAttribute('tabindex', '0');

/* -------------------------------------------------------------------------
 * Footer/status summary.
 * ---------------------------------------------------------------------- */

$count = count($data['rows']);
$count_text = sprintf(_('%d events shown'), $count);
$limit_text = $data['query_active']
    ? sprintf(_('Search maximum %d'), (int) $data['result_limit'])
    : '';

$footer_items = [
    (new CSpan($count_text))
        ->addClass('nps-wally-count')
        ->setAttribute('aria-live', 'polite')
        ->setAttribute('data-singular', _('event shown'))
        ->setAttribute('data-plural', _('events shown')),
    (new CSpan([
        (new CSpan((string) $grant_count))->addClass('nps-wally-grant-count'),
        ' '._('Grant')
    ]))->addClass('nps-wally-stat')->addClass('is-grant'),
    (new CSpan([
        (new CSpan((string) $deny_count))->addClass('nps-wally-deny-count'),
        ' '._('Deny')
    ]))->addClass('nps-wally-stat')->addClass('is-deny'),
    (new CSpan(_('Updated')))
        ->addClass('nps-wally-updated')
        ->setAttribute('data-prefix', _('Updated'))
        ->setAttribute('aria-live', 'polite'),
    (new CSpan(_('Build').': '._('Generic / architecture-independent')))
        ->addClass('nps-wally-build')
        ->setAttribute(
            'title',
            _('This Zabbix module is platform-independent PHP/JavaScript, not a native machine-code build')
        )
];

if ($limit_text !== '') {
    $footer_items[] = (new CSpan($limit_text))->addClass('nps-wally-limit');
}

$footer_summary = (new CDiv($footer_items))
    ->addClass('nps-wally-footer-summary');

$source = (new CSpan([
    (new CSpan(_('Source').': '))->addClass('nps-wally-source-label'),
    (new CSpan((string) $data['item_name']))->addClass('nps-wally-source-value')
]))
    ->addClass('nps-wally-source')
    ->setAttribute('title', (string) $data['item_name']);

$footer = (new CDiv([$footer_summary, $source]))
    ->addClass('nps-wally-footer');

$root = (new CDiv([$toolbar, $scroller, $footer]))
    ->addClass('nps-wally');

(new CWidgetView($data))
    ->addItem($root)
    ->show();
