/**
 * WHERE'S WALLY — NPS Event Monitor client controller.
 *
 * Copyright (C) 2026 Shannon Smith and Carlo Cunanan
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Auto-scroll is the live-feed switch. When enabled, the widget performs a
 * lightweight one-second poll and follows the newest event. When disabled,
 * the current rows are held in place. Historical search remains user-driven:
 * text/receipt-date criteria are submitted only when Enter is pressed.
 */
class WidgetNpsWheresWally extends CWidget {

    _searchText = '';
    _dateFrom = '';
    _dateTo = '';
    _historicalUpdateRequested = false;
    _clearedBefore = '';
    _autoScrollEnabled = true;
    _livePollTimer = null;
    _livePollIntervalMs = 1000;

    onInitialize() {
        super.onInitialize();

        this._searchText = '';
        this._dateFrom = '';
        this._dateTo = '';
        this._historicalUpdateRequested = false;
        this._clearedBefore = '';
        this._autoScrollEnabled = true;
        this._livePollTimer = null;
        this._livePollIntervalMs = 1000;
    }

    onActivate() {
        super.onActivate();
        this._syncLivePolling();
    }

    onDeactivate() {
        this._stopLivePolling();
        super.onDeactivate();
    }

    onDestroy() {
        this._stopLivePolling();
        super.onDestroy();
    }

    getUpdateRequestData() {
        const data = super.getUpdateRequestData();
        const searchText = this._searchText.trim();

        if (searchText !== '') {
            data.search_text = searchText;
        }

        if (this._dateFrom !== '') {
            data.date_from = this._dateFrom;
        }

        if (this._dateTo !== '') {
            data.date_to = this._dateTo;
        }

        return data;
    }

    promiseUpdate() {
        if (this._isHistoricalQueryActive()) {
            if (!this._historicalUpdateRequested) {
                return Promise.resolve();
            }

            this._historicalUpdateRequested = false;
            return super.promiseUpdate();
        }

        // Auto-scroll OFF is a true hold: periodic Zabbix refresh attempts are
        // deliberately ignored until the operator re-enables the live feed.
        // A one-shot request is still allowed when Reset search returns from a
        // historical view so the held display contains a current live snapshot.
        if (!this._autoScrollEnabled && !this._historicalUpdateRequested) {
            return Promise.resolve();
        }

        this._historicalUpdateRequested = false;
        return super.promiseUpdate();
    }

    setContents(response) {
        super.setContents(response);
        this._bindControls();
    }

    _bindControls() {
        const root = this._body.querySelector('.nps-wally');

        if (root === null) {
            return;
        }

        const search = root.querySelector('.nps-wally-filter');
        const dateFrom = root.querySelector('.nps-wally-date-from');
        const dateTo = root.querySelector('.nps-wally-date-to');
        const resetSearch = root.querySelector('.nps-wally-search-reset');
        const exportButton = root.querySelector('.nps-wally-export');
        const clearButton = root.querySelector('.nps-wally-clear');
        const autoScroll = root.querySelector('.nps-wally-autoscroll');
        const scroller = root.querySelector('.nps-wally-scroller');

        if (search === null || dateFrom === null || dateTo === null || resetSearch === null
                || exportButton === null || clearButton === null || autoScroll === null
                || scroller === null) {
            return;
        }

        search.value = this._searchText;
        dateFrom.value = this._dateFrom;
        dateTo.value = this._dateTo;
        autoScroll.checked = this._autoScrollEnabled;
        this._updateModeIndicator(root);

        const submitHistoricalSearch = (event) => {
            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            this._searchText = search.value;
            this._dateFrom = dateFrom.value;
            this._dateTo = dateTo.value;

            if (this._dateFrom !== '' && this._dateTo !== '' && this._dateFrom > this._dateTo) {
                [this._dateFrom, this._dateTo] = [this._dateTo, this._dateFrom];
                dateFrom.value = this._dateFrom;
                dateTo.value = this._dateTo;
            }

            this._requestHistoricalUpdate();
        };

        search.addEventListener('keydown', submitHistoricalSearch);
        dateFrom.addEventListener('keydown', submitHistoricalSearch);
        dateTo.addEventListener('keydown', submitHistoricalSearch);

        resetSearch.addEventListener('click', () => {
            this._searchText = '';
            this._dateFrom = '';
            this._dateTo = '';
            search.value = '';
            dateFrom.value = '';
            dateTo.value = '';
            this._requestHistoricalUpdate();
            search.focus();
        });

        exportButton.addEventListener('click', () => this._exportVisibleRows(root));

        clearButton.addEventListener('click', () => {
            this._clearedBefore = this._newestReceiptKey(root, this._clearedBefore);
            this._applyVisibilityRules(root);
        });

        autoScroll.addEventListener('change', () => {
            this._autoScrollEnabled = autoScroll.checked;

            if (this._autoScrollEnabled) {
                scroller.scrollTop = 0;
                this._syncLivePolling();

                if (this._state === WIDGET_STATE_ACTIVE && !this._isHistoricalQueryActive()) {
                    this._startUpdating();
                }
            }
            else {
                this._stopLivePolling();

                if (this._state === WIDGET_STATE_ACTIVE && !this._isHistoricalQueryActive()) {
                    this._stopUpdating({do_abort: true});
                }
            }

            this._updateModeIndicator(root);
        });

        root.addEventListener('click', (event) => {
            const target = event.target;

            if (!(target instanceof Element)) {
                return;
            }

            const button = target.closest('.nps-wally-details-button');

            if (button !== null) {
                this._toggleDetails(button);
            }
        });

        this._applyVisibilityRules(root);

        if (this._autoScrollEnabled) {
            scroller.scrollTop = 0;
        }
    }

    _applyVisibilityRules(root) {
        let visibleCount = 0;

        for (const row of root.querySelectorAll('.nps-wally-event-row')) {
            const receivedKey = row.dataset.receivedKey || '';
            const isAfterClear = this._clearedBefore === ''
                || receivedKey > this._clearedBefore;
            const detailRow = row.nextElementSibling;

            row.hidden = !isAfterClear;

            if (detailRow?.classList.contains('nps-wally-detail-row') && !isAfterClear) {
                detailRow.hidden = true;
            }

            if (isAfterClear) {
                visibleCount++;
            }
        }

        this._updateVisibleCount(root, visibleCount);
    }

    _requestHistoricalUpdate() {
        this._historicalUpdateRequested = true;
        this._clearedBefore = '';
        this._syncLivePolling();

        if (this._state === WIDGET_STATE_ACTIVE) {
            this._startUpdating();
        }
    }

    _syncLivePolling() {
        if (this._state === WIDGET_STATE_ACTIVE
                && this._autoScrollEnabled
                && !this._isHistoricalQueryActive()) {
            this._startLivePolling();
        }
        else {
            this._stopLivePolling();
        }
    }

    _startLivePolling() {
        if (this._livePollTimer !== null) {
            return;
        }

        this._livePollTimer = setInterval(() => {
            if (this._state !== WIDGET_STATE_ACTIVE
                    || !this._autoScrollEnabled
                    || this._isHistoricalQueryActive()) {
                return;
            }

            // CWidgetBase._update() already prevents overlapping requests and
            // defers while the operator is actively interacting with the widget.
            this._update();
        }, this._livePollIntervalMs);
    }

    _stopLivePolling() {
        if (this._livePollTimer === null) {
            return;
        }

        clearInterval(this._livePollTimer);
        this._livePollTimer = null;
    }

    _updateModeIndicator(root) {
        const indicator = root.querySelector('.nps-wally-live');

        if (indicator === null) {
            return;
        }

        indicator.classList.remove('is-live', 'is-search', 'is-hold');

        if (this._isHistoricalQueryActive()) {
            indicator.textContent = 'SEARCH';
            indicator.classList.add('is-search');
            indicator.title = 'Server-side retained-history search; press Enter to refresh the result set';
        }
        else if (this._autoScrollEnabled) {
            indicator.textContent = 'LIVE';
            indicator.classList.add('is-live');
            indicator.title = 'Auto-scroll is on: checking for new NPS events every second';
        }
        else {
            indicator.textContent = 'HOLD';
            indicator.classList.add('is-hold');
            indicator.title = 'Auto-scroll is off: the current event list is held in place';
        }
    }

    _isHistoricalQueryActive() {
        return this._searchText.trim() !== '' || this._dateFrom !== '' || this._dateTo !== '';
    }

    _toggleDetails(button) {
        const eventRow = button.closest('.nps-wally-event-row');
        const detailRow = eventRow?.nextElementSibling;

        if (detailRow === null || detailRow === undefined
                || !detailRow.classList.contains('nps-wally-detail-row')) {
            return;
        }

        const willOpen = detailRow.hidden;

        detailRow.hidden = !willOpen;
        button.setAttribute('aria-expanded', String(willOpen));
        button.textContent = willOpen
            ? (button.dataset.hideLabel || 'Hide')
            : (button.dataset.showLabel || 'Details');
    }

    _newestReceiptKey(root, initial) {
        let newest = initial;

        for (const row of root.querySelectorAll('.nps-wally-event-row')) {
            const key = row.dataset.receivedKey || '';

            if (key > newest) {
                newest = key;
            }
        }

        return newest;
    }

    _exportVisibleRows(root) {
        const headings = [
            'Event ID',
            'Time',
            'Account',
            'Domain',
            'Name',
            'Access point',
            'Location',
            'Device MAC',
            'IP address',
            'Result'
        ];
        const lines = [headings.map((value) => this._csvCell(value)).join(',')];

        for (const row of root.querySelectorAll('.nps-wally-event-row:not([hidden])')) {
            const values = [...row.querySelectorAll('td[data-export]')]
                .map((cell) => cell.textContent.trim());

            lines.push(values.map((value) => this._csvCell(value)).join(','));
        }

        const csv = `\uFEFF${lines.join('\r\n')}`;
        const blob = new Blob([csv], {type: 'text/csv;charset=utf-8'});
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        const timestamp = new Date().toISOString().replace(/[:.]/g, '-');

        link.href = url;
        link.download = `wheres-wally-nps-events-${timestamp}.csv`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        URL.revokeObjectURL(url);
    }

    /**
     * Quote a CSV field and neutralise spreadsheet formula prefixes.
     *
     * Event text can contain administrator-controlled or network-supplied data.
     * Prefixing a leading formula marker with an apostrophe prevents Excel and
     * similar spreadsheet software from evaluating the cell as a formula.
     */
    _csvCell(value) {
        const text = String(value);
        const safe = /^[=+\-@\t\r]/.test(text) ? `'${text}` : text;

        return `"${safe.replace(/"/g, '""')}"`;
    }

    _updateVisibleCount(root, visibleCount) {
        const count = root.querySelector('.nps-wally-count');

        if (count !== null) {
            count.textContent = `${visibleCount} event${visibleCount === 1 ? '' : 's'} shown`;
        }
    }
}
