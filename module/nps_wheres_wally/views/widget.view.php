<?php declare(strict_types = 1);

/**
 * WHERE'S WALLY — NPS event-table presentation.
 *
 * Copyright (C) 2026 Shannon Smith and Carlo Cunanan
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * @var CView $this
 * @var array<string, mixed> $data
 */

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

$filter = (new CTag('input', false))
    ->setAttribute('type', 'search')
    ->setAttribute('placeholder', _('Search retained history — press Enter'))
    ->setAttribute('aria-label', _('Search retained NPS history; press Enter to run'))
    ->setAttribute('autocomplete', 'off')
    ->addClass('nps-wally-filter');

$date_from = (new CTag('input', false))
    ->setAttribute('type', 'date')
    ->setAttribute('aria-label', _('Received from date'))
    ->setAttribute(
        'title',
        _('Optional Zabbix receipt-date start; press Enter to run retained-history search')
    )
    ->addClass('nps-wally-date')
    ->addClass('nps-wally-date-from');

$date_to = (new CTag('input', false))
    ->setAttribute('type', 'date')
    ->setAttribute('aria-label', _('Received to date'))
    ->setAttribute(
        'title',
        _('Optional Zabbix receipt-date end; press Enter to run retained-history search')
    )
    ->addClass('nps-wally-date')
    ->addClass('nps-wally-date-to');

$reset_search_button = (new CTag('button', true, _('Reset search')))
    ->setAttribute('type', 'button')
    ->setAttribute('title', _('Clear search text and receipt-date range; return to live view'))
    ->addClass('nps-wally-button')
    ->addClass('nps-wally-search-reset');

$export_button = (new CTag('button', true, '↧ '._('Export')))
    ->setAttribute('type', 'button')
    ->setAttribute('title', _('Export currently displayed rows as CSV'))
    ->addClass('nps-wally-button')
    ->addClass('nps-wally-export');

$clear_button = (new CTag('button', true, '× '._('Clear')))
    ->setAttribute('type', 'button')
    ->setAttribute('title', _('Hide events currently displayed; monitoring history is not deleted'))
    ->addClass('nps-wally-button')
    ->addClass('nps-wally-clear');

$auto_scroll = (new CTag('input', false))
    ->setAttribute('type', 'checkbox')
    ->setAttribute('checked', 'checked')
    ->setAttribute('title', _('On: live one-second updates; Off: hold the current event list'))
    ->addClass('nps-wally-autoscroll');

$toolbar = (new CDiv([
    (new CDiv([
        (new CSpan("WHERE'S WALLY"))->addClass('nps-wally-title'),
        (new CSpan(_('NPS Event Monitor · 6272 Grant · 6273 Deny')))
            ->addClass('nps-wally-subtitle')
    ]))->addClass('nps-wally-brand'),
    (new CDiv([
        $filter,
        (new CTag('label', true, [_('Received from').' ', $date_from]))
            ->addClass('nps-wally-date-label'),
        (new CTag('label', true, [_('Received to').' ', $date_to]))
            ->addClass('nps-wally-date-label'),
        $reset_search_button,
        $export_button,
        $clear_button,
        (new CTag('label', true, [$auto_scroll, ' '._('Auto-scroll')]))
            ->addClass('nps-wally-auto-label'),
        (new CSpan($data['query_active'] ? _('SEARCH') : _('LIVE')))
            ->addClass('nps-wally-live')
            ->addClass($data['query_active'] ? 'is-search' : 'is-live')
            ->setAttribute(
                'title',
                $data['query_active']
                    ? _('Server-side retained-history search; date bounds use Zabbix receipt time; maximum 200 rows returned')
                    : _('Auto-scroll on: checks for new NPS events every second; turn it off to hold the current list')
            )
    ]))->addClass('nps-wally-controls')
]))->addClass('nps-wally-toolbar');

$headers = [
    _('Event'),
    _('Time'),
    _('Account'),
    _('Domain'),
    _('Name'),
    _('Access point'),
    _('Location'),
    _('Device MAC'),
    _('IP address'),
    _('Result'),
    _('Details')
];

$header_cells = [];
foreach ($headers as $header) {
    $header_cells[] = (new CTag('th', true, $header))
        ->setAttribute('scope', 'col');
}

$body_rows = [];
foreach ($data['rows'] as $row) {
    $result = (new CSpan((string) $row['status_long']))
        ->addClass('nps-wally-result')
        ->addClass($row['status'] === 'Grant' ? 'is-grant' : 'is-deny')
        ->setAttribute('title', (string) $row['reason']);

    $details_button = (new CTag('button', true, _('Details')))
        ->setAttribute('type', 'button')
        ->setAttribute('aria-expanded', 'false')
        ->setAttribute('data-show-label', _('Details'))
        ->setAttribute('data-hide-label', _('Hide'))
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
        ->setAttribute('data-received-key', (string) $row['received_key']);

    $summary_items = [
        (new CSpan(_('Received').': '.(string) $row['received_time']))->addClass('nps-detail-pill'),
        (new CSpan(_('SSID').': '.(string) $row['ssid']))->addClass('nps-detail-pill'),
        (new CSpan(_('Network policy').': '.(string) $row['network_policy']))->addClass('nps-detail-pill'),
        (new CSpan(_('Connection policy').': '.(string) $row['connection_request_policy']))->addClass('nps-detail-pill'),
        (new CSpan(_('Authentication').': '.(string) $row['authentication_type']))->addClass('nps-detail-pill'),
        (new CSpan(_('EAP').': '.(string) $row['eap_type']))->addClass('nps-detail-pill')
    ];

    if ($row['reason_code'] !== '') {
        $summary_items[] = (new CSpan(_('Reason code').': '.(string) $row['reason_code']))
            ->addClass('nps-detail-pill')
            ->addClass('is-deny');
    }

    if ($row['reason'] !== '') {
        $summary_items[] = (new CSpan((string) $row['reason']))->addClass('nps-detail-reason');
    }

    $detail_content = (new CDiv([
        new CDiv($summary_items),
        (new CTag('pre', true, (string) $row['message']))->addClass('nps-wally-message')
    ]))->addClass('nps-wally-detail-content');

    $detail_row = (new CTag(
        'tr',
        true,
        (new CTag('td', true, $detail_content))
            ->setAttribute('colspan', (string) count($headers))
    ))
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

$count = count($data['rows']);
$count_text = $data['query_active']
    ? sprintf(_('%1$d matching events shown (max %2$d)'), $count, (int) $data['result_limit'])
    : sprintf(_('%1$d newest events shown'), $count);

$footer = (new CDiv([
    (new CSpan($count_text))
        ->addClass('nps-wally-count')
        ->setAttribute('aria-live', 'polite'),
    (new CSpan((string) $data['item_name']))
        ->addClass('nps-wally-source')
        ->setAttribute('title', (string) $data['item_name'])
]))->addClass('nps-wally-footer');

$root = (new CDiv([$toolbar, $scroller, $footer]))
    ->addClass('nps-wally');

(new CWidgetView($data))
    ->addItem($root)
    ->show();
