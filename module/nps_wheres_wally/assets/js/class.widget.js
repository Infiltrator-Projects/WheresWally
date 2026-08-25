/**
 * WHERE'S WALLY — NPS Event Monitor client controller.
 *
 * Copyright (C) 2026 Shannon Smith and Carlo Cunanan
 * SPDX-License-Identifier: GPL-3.0-or-later
 *
 * Client state model
 * ------------------
 * Zabbix owns widget activation/deactivation and the actual AJAX request. This
 * class adds a small operator-facing state machine on top of that lifecycle:
 *
 *   LIVE   Search/date criteria are blank and Auto-scroll is enabled. A
 *          widget-owned one-second timer asks Zabbix for a fresh bounded view
 *          and the scroller follows the newest row.
 *
 *   HOLD   Search/date criteria are blank and Auto-scroll is disabled. Periodic
 *          updates are suppressed so the rendered rows remain stable while an
 *          operator reads or investigates them.
 *
 *   SEARCH At least one retained-history criterion is active. Searches are
 *          explicit operations only; Enter or the Search button permits one
 *          request and periodic refresh attempts become no-ops.
 *
 * Invariants
 * ----------
 * - Auto-scroll controls LIVE versus HOLD; it is not merely a scroll-position
 *   preference.
 * - Historical criteria always suspend the one-second live timer.
 * - Clear is browser-only state and never deletes Zabbix history.
 * - At most one Zabbix update can be in flight because CWidgetBase._update()
 *   owns request overlap protection.
 */
class WidgetNpsWheresWally extends CWidget {

    // Transient query state. These values deliberately are not persisted in the
    // dashboard widget configuration.
    _searchText = '';
    _dateFrom = '';
    _dateTo = '';

    // Permission for the next framework update. The flag is also used for the
    // one-shot live snapshot requested when a held historical view is reset.
    _explicitUpdateRequested = false;

    // Fixed-width receipt key used by non-destructive Clear semantics.
    _clearedBefore = '';

    // Live-feed state and scheduling. One second is intentionally independent of
    // Zabbix's normal dashboard refresh menu, whose minimum interval is 10 sec.
    _autoScrollEnabled = true;
    _livePollTimer = null;
    _livePollIntervalMs = 1000;

    // Browser-local presentation metadata only; it never affects event timing or
    // server-side search boundaries.
    _lastRefreshAt = null;

    /** Initialise transient state before any widget DOM exists. */
    onInitialize() {
        super.onInitialize();

        this._searchText = '';
        this._dateFrom = '';
        this._dateTo = '';
        this._explicitUpdateRequested = false;
        this._clearedBefore = '';
        this._autoScrollEnabled = true;
        this._livePollTimer = null;
        this._livePollIntervalMs = 1000;
        this._lastRefreshAt = null;
    }

    /** Start the custom live timer when Zabbix activates this dashboard page. */
    onActivate() {
        super.onActivate();
        this._syncLivePolling();
    }

    /** Stop module-owned timers before the dashboard page becomes inactive. */
    onDeactivate() {
        this._stopLivePolling();
        super.onDeactivate();
    }

    /** Ensure no timer survives deletion of the widget instance. */
    onDestroy() {
        this._stopLivePolling();
        super.onDestroy();
    }

    /**
     * Add transient retained-history criteria to Zabbix's ordinary widget
     * request. Date values remain YYYY-MM-DD strings so the server, not the
     * browser, converts them in the active Zabbix frontend timezone.
     */
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

    /**
     * Gate framework refreshes according to the three-state model above.
     *
     * Zabbix may still invoke `promiseUpdate()` because of its own refresh rate,
     * resize handling or retry logic. Returning an already-resolved Promise is
     * the supported way to make those attempts harmless in HOLD and SEARCH.
     */
    promiseUpdate() {
        if (this._isHistoricalQueryActive()) {
            if (!this._explicitUpdateRequested) {
                return Promise.resolve();
            }

            this._explicitUpdateRequested = false;
            return super.promiseUpdate();
        }

        if (!this._autoScrollEnabled && !this._explicitUpdateRequested) {
            return Promise.resolve();
        }

        this._explicitUpdateRequested = false;
        return super.promiseUpdate();
    }

    /**
     * Record when a real server response was rendered, then re-bind controls.
     * Zabbix replaces the widget body on every successful response, so event
     * listeners must be attached to the new DOM each time.
     */
    setContents(response) {
        this._lastRefreshAt = new Date();
        super.setContents(response);
        this._bindControls();

        // Zabbix starts its native dashboard refresh interval whenever the
        // widget becomes active or `_startUpdating()` is used for an explicit
        // request. WHERE'S WALLY has stricter semantics: LIVE is driven by the
        // one-second timer below, HOLD must remain motionless, and SEARCH is
        // operator-driven. Once this response is safely rendered, retire the
        // native interval so a second scheduler cannot generate duplicate
        // refreshes alongside the module-owned state machine.
        if (this._state === WIDGET_STATE_ACTIVE) {
            this._stopUpdating({do_abort: false});
            this._syncLivePolling();
        }
    }

    /**
     * Restore transient values into the newly rendered controls and attach all
     * operator interactions for this DOM generation.
     */
    _bindControls() {
        const root = this._body.querySelector('.nps-wally');

        if (root === null) {
            return;
        }

        const search = root.querySelector('.nps-wally-filter');
        const searchButton = root.querySelector('.nps-wally-search-button');
        const dateFrom = root.querySelector('.nps-wally-date-from');
        const dateTo = root.querySelector('.nps-wally-date-to');
        const resetSearch = root.querySelector('.nps-wally-search-reset');
        const exportButton = root.querySelector('.nps-wally-export');
        const clearButton = root.querySelector('.nps-wally-clear');
        const autoScroll = root.querySelector('.nps-wally-autoscroll');
        const scroller = root.querySelector('.nps-wally-scroller');

        if (search === null || searchButton === null || dateFrom === null || dateTo === null
                || resetSearch === null || exportButton === null || clearButton === null
                || autoScroll === null || scroller === null) {
            return;
        }

        // Server rendering is intentionally stateless for these controls. Put the
        // browser instance's state back after every successful widget refresh.
        search.value = this._searchText;
        dateFrom.value = this._dateFrom;
        dateTo.value = this._dateTo;
        autoScroll.checked = this._autoScrollEnabled;

        this._updateModeIndicator(root);
        this._updateRefreshStamp(root);

        const runSearch = () => {
            this._searchText = search.value;
            this._dateFrom = dateFrom.value;
            this._dateTo = dateTo.value;

            // Date strings sort chronologically in YYYY-MM-DD form. Swapping a
            // reversed pair is friendlier than silently returning zero results.
            if (this._dateFrom !== '' && this._dateTo !== '' && this._dateFrom > this._dateTo) {
                [this._dateFrom, this._dateTo] = [this._dateTo, this._dateFrom];
                dateFrom.value = this._dateFrom;
                dateTo.value = this._dateTo;
            }

            this._requestExplicitUpdate();
        };

        const runSearchOnEnter = (event) => {
            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();
            event.stopPropagation();
            runSearch();
        };

        search.addEventListener('keydown', runSearchOnEnter);
        dateFrom.addEventListener('keydown', runSearchOnEnter);
        dateTo.addEventListener('keydown', runSearchOnEnter);
        searchButton.addEventListener('click', runSearch);

        resetSearch.addEventListener('click', () => {
            this._searchText = '';
            this._dateFrom = '';
            this._dateTo = '';
            search.value = '';
            dateFrom.value = '';
            dateTo.value = '';

            // When HOLD is active this intentionally permits one fresh live
            // snapshot, after which HOLD resumes. In LIVE it simply returns to
            // the normal one-second feed.
            this._requestExplicitUpdate();
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

                // Re-enabling live mode should not wait for the next one-second
                // timer tick; request a fresh snapshot immediately.
                if (this._state === WIDGET_STATE_ACTIVE && !this._isHistoricalQueryActive()) {
                    this._startUpdating();
                }
            }
            else {
                this._stopLivePolling();

                // Abort an in-flight live refresh so its late response cannot
                // replace the rows after the operator has selected HOLD.
                if (this._state === WIDGET_STATE_ACTIVE && !this._isHistoricalQueryActive()) {
                    this._stopUpdating({do_abort: true});
                }
            }

            this._updateModeIndicator(root);
        });

        // Detail rows are recreated after each response, so delegate from the
        // stable root rather than attaching a separate listener to every button.
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
     * Apply browser-only Clear state and recalculate the visible summary. Detail
     * rows are paired immediately after their event row and must be hidden with
     * the parent event when it falls behind the Clear boundary.
     */
    _applyVisibilityRules(root) {
        let visibleCount = 0;
        let grantCount = 0;
        let denyCount = 0;

        for (const row of root.querySelectorAll('.nps-wally-event-row')) {
            const receivedKey = row.dataset.receivedKey || '';
            const isAfterClear = this._clearedBefore === ''
                || receivedKey > this._clearedBefore;
            const detailRow = row.nextElementSibling;

            row.hidden = !isAfterClear;

            if (detailRow?.classList.contains('nps-wally-detail-row') && !isAfterClear) {
                detailRow.hidden = true;
            }

            if (!isAfterClear) {
                continue;
            }

            visibleCount++;

            if (row.dataset.result === 'Grant') {
                grantCount++;
            }
            else if (row.dataset.result === 'Deny') {
                denyCount++;
            }
        }

        this._updateVisibleSummary(root, visibleCount, grantCount, denyCount);
    }

    /**
     * Permit one framework request after an explicit operator action and reset
     * Clear state because the operator has deliberately changed the result set.
     */
    _requestExplicitUpdate() {
        this._explicitUpdateRequested = true;
        this._clearedBefore = '';
        this._syncLivePolling();

        if (this._state === WIDGET_STATE_ACTIVE) {
            this._startUpdating();
        }
    }

    /** Keep the custom one-second timer aligned with LIVE/HOLD/SEARCH state. */
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

    /**
     * Start a single self-rescheduling timer rather than an unbounded interval.
     *
     * Each tick delegates to CWidgetBase._update(), which owns overlap protection,
     * retry behaviour, frontend permission handling and rendering. Hidden browser
     * tabs are skipped: the next visible tick refreshes within one second, while
     * an unattended tab generates no needless one-second server traffic.
     */
    _startLivePolling() {
        if (this._livePollTimer !== null) {
            return;
        }

        this._livePollTimer = setTimeout(() => {
            this._livePollTimer = null;

            if (this._state !== WIDGET_STATE_ACTIVE
                    || !this._autoScrollEnabled
                    || this._isHistoricalQueryActive()) {
                return;
            }

            if (typeof document === 'undefined' || !document.hidden) {
                this._update();
            }

            this._startLivePolling();
        }, this._livePollIntervalMs);
    }

    /** Cancel the module-owned timer without touching Zabbix's lifecycle state. */
    _stopLivePolling() {
        if (this._livePollTimer === null) {
            return;
        }

        clearTimeout(this._livePollTimer);
        this._livePollTimer = null;
    }

    /**
     * Render LIVE/HOLD/SEARCH using translated copy supplied by PHP data
     * attributes. JavaScript contains English only as an emergency fallback.
     */
    _updateModeIndicator(root) {
        const indicator = root.querySelector('.nps-wally-mode');

        if (indicator === null) {
            return;
        }

        let mode = 'hold';

        if (this._isHistoricalQueryActive()) {
            mode = 'search';
        }
        else if (this._autoScrollEnabled) {
            mode = 'live';
        }

        const fallback = {
            live: ['LIVE', 'Auto-scroll is on: checking for new NPS events every second'],
            hold: ['HOLD', 'Auto-scroll is off: the current event list is held in place'],
            search: ['SEARCH', 'Server-side retained-history search; run explicitly to refresh']
        };
        const label = indicator.dataset[`${mode}Label`] || fallback[mode][0];
        const title = indicator.dataset[`${mode}Title`] || fallback[mode][1];

        indicator.classList.remove('is-live', 'is-search', 'is-hold');
        indicator.classList.add(`is-${mode}`);
        indicator.textContent = label;
        indicator.title = title;
    }

    /** Update browser-local feedback showing when a real response was rendered. */
    _updateRefreshStamp(root) {
        const stamp = root.querySelector('.nps-wally-updated');

        if (stamp === null || !(this._lastRefreshAt instanceof Date)) {
            return;
        }

        const prefix = stamp.dataset.prefix || 'Updated';
        const time = this._lastRefreshAt.toLocaleTimeString([], {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });

        stamp.textContent = `${prefix} ${time}`;
    }

    /** Return whether this browser instance currently represents a history query. */
    _isHistoricalQueryActive() {
        return this._searchText.trim() !== '' || this._dateFrom !== '' || this._dateTo !== '';
    }

    /** Toggle the detail row immediately following the selected event row. */
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

    /** Return the newest fixed-width receipt key currently present in the DOM. */
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

    /**
     * Export exactly the currently visible primary rows.
     *
     * Column headings are read from the rendered table rather than duplicated in
     * JavaScript, which keeps CSV output aligned with translated UI labels and
     * future column changes.
     */
    _exportVisibleRows(root) {
        const headings = [...root.querySelectorAll('th[data-export-heading]')]
            .map((heading) => heading.textContent.trim());
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
     * NPS text can include network- or administrator-controlled values. Excel and
     * similar applications may evaluate cells beginning with `=`, `+`, `-`, `@`,
     * tab or carriage return as formulas. Leading whitespace before a formula
     * marker is treated defensively as formula-like as well.
     */
    _csvCell(value) {
        const text = String(value);
        const formulaLike = /^[=+\-@\t\r]/.test(text)
            || /^[ \t\r\n]+[=+\-@]/.test(text);
        const safe = formulaLike ? `'${text}` : text;

        return `"${safe.replace(/"/g, '""')}"`;
    }

    /** Synchronise footer counts with rows remaining after Clear. */
    _updateVisibleSummary(root, visibleCount, grantCount, denyCount) {
        const count = root.querySelector('.nps-wally-count');
        const grants = root.querySelector('.nps-wally-grant-count');
        const denies = root.querySelector('.nps-wally-deny-count');

        if (count !== null) {
            const singular = count.dataset.singular || 'event shown';
            const plural = count.dataset.plural || 'events shown';
            count.textContent = `${visibleCount} ${visibleCount === 1 ? singular : plural}`;
        }

        if (grants !== null) {
            grants.textContent = String(grantCount);
        }

        if (denies !== null) {
            denies.textContent = String(denyCount);
        }
    }
}
