/**
 * WHERE'S WALLY — NPS Event Monitor client controller.
 *
 * Copyright (C) 2026 Shannon Smith and Carlo Cunanan
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Normal live refresh is owned by Zabbix. Historical search is user-driven:
 * text/date criteria are submitted only when Enter is pressed and are added to
 * the ordinary widget update request. No keystroke timer or page reload is used.
 * Repeated dashboard refreshes are suppressed for an active historical query.
 */
class WidgetNpsWheresWally extends CWidget {

    /** @type {string} Server-side retained-history search text. */
    _searchText = '';

    /** @type {string} YYYY-MM-DD lower date boundary in browser local time. */
    _dateFrom = '';

    /** @type {string} YYYY-MM-DD upper date boundary in browser local time. */
    _dateTo = '';

    /** @type {boolean} Allow exactly the next active historical query to run. */
    _historicalUpdateRequested = false;

    /**
     * Highest Zabbix receipt key visible when Clear was pressed. Events at or
     * below this value are hidden locally; no Zabbix history is deleted.
     * @type {string}
     */
    _clearedBefore = '';

    /** @type {boolean} Whether each refresh returns the table to the newest row. */
    _autoScrollEnabled = true;

    onInitialize() {
        super.onInitialize();

        this._searchText = '';
        this._dateFrom = '';
        this._dateTo = '';
        this._historicalUpdateRequested = false;
        this._clearedBefore = '';
        this._autoScrollEnabled = true;
    }

    /**
     * Add transient search criteria to the standard Zabbix widget update body.
     */
    getUpdateRequestData() {
        const data = super.getUpdateRequestData();
        const searchText = this._searchText.trim();
        const timeFrom = this._dateToTimestamp(this._dateFrom, false);
        const timeTill = this._dateToTimestamp(this._dateTo, true);

        if (searchText !== '') {
            data.search_text = searchText;
        }

        if (timeFrom !== null) {
            data.time_from = timeFrom;
        }

        if (timeTill !== null) {
            data.time_till = timeTill;
        }

        return data;
    }

    /**
     * Keep normal live mode on Zabbix's periodic refresh cycle. Once any search
     * criterion is active, queries run only when the user changes that criterion.
     * This avoids re-scanning a very large retained history every refresh period.
     */
    promiseUpdate() {
        if (this._isHistoricalQueryActive()) {
            if (!this._historicalUpdateRequested) {
                return Promise.resolve();
            }

            this._historicalUpdateRequested = false;
        }
        else {
            this._historicalUpdateRequested = false;
        }

        return super.promiseUpdate();
    }

    setContents(response) {
        super.setContents(response);
        this._bindControls();
    }

    /**
     * Bind behaviour to the current widget DOM and restore transient state.
     */
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

        const submitHistoricalSearch = (event) => {
            if (event.key !== 'Enter') {
                return;
            }

            // Enter runs an in-widget AJAX update only; never submit/reload the page.
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
            }
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

    /**
     * Apply the browser-local Clear boundary. Text/date filtering is deliberately
     * absent here because those operations now run against retained server history.
     */
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

    /**
     * Run one immediate widget update with the current search/date criteria.
     */
    _requestHistoricalUpdate() {
        this._historicalUpdateRequested = true;
        this._clearedBefore = '';

        if (this._state === WIDGET_STATE_ACTIVE) {
            this._startUpdating();
        }
    }

    _isHistoricalQueryActive() {
        return this._searchText.trim() !== '' || this._dateFrom !== '' || this._dateTo !== '';
    }

    /**
     * Convert an HTML date input to an epoch timestamp in browser local time.
     * The upper boundary represents the final second of the selected day.
     */
    _dateToTimestamp(value, endOfDay) {
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
            return null;
        }

        const [year, month, day] = value.split('-').map(Number);
        const date = endOfDay
            ? new Date(year, month - 1, day + 1, 0, 0, 0, 0)
            : new Date(year, month - 1, day, 0, 0, 0, 0);
        const timestamp = Math.floor(date.getTime() / 1000) - (endOfDay ? 1 : 0);

        return Number.isFinite(timestamp) ? timestamp : null;
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

    _csvCell(value) {
        return `"${String(value).replace(/"/g, '""')}"`;
    }

    _updateVisibleCount(root, visibleCount) {
        const count = root.querySelector('.nps-wally-count');

        if (count !== null) {
            count.textContent = `${visibleCount} event${visibleCount === 1 ? '' : 's'} shown`;
        }
    }
}
