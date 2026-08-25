#!/usr/bin/env node
'use strict';

const fs = require('fs');
const vm = require('vm');

const source = fs.readFileSync(
    __dirname + '/../module/nps_wheres_wally/assets/js/class.widget.js',
    'utf8'
);

class StubWidget {
    getUpdateRequestData() { return {}; }
    promiseUpdate() {
        this._stubUpdateCount = (this._stubUpdateCount || 0) + 1;
        return Promise.resolve();
    }
    onInitialize() {}
    onActivate() {}
    onDeactivate() {}
    onDestroy() {}
}
const context = {
    CWidget: StubWidget,
    WIDGET_STATE_ACTIVE: 1,
    console
};

vm.createContext(context);
vm.runInContext(source + '\nthis.WidgetNpsWheresWally = WidgetNpsWheresWally;', context);

const widget = new context.WidgetNpsWheresWally();

function same(label, actual, expected) {
    if (actual !== expected) {
        throw new Error(`${label}\nExpected: ${expected}\nActual: ${actual}`);
    }
}

same('Ordinary CSV field is quoted.', widget._csvCell('hello'), '"hello"');
same('Quotes are doubled.', widget._csvCell('a"b'), '"a""b"');
same('Formula equals is neutralised.', widget._csvCell('=2+2'), '"\'=2+2"');
same('Formula plus is neutralised.', widget._csvCell('+SUM(A1:A2)'), '"\'+SUM(A1:A2)"');
same('Formula minus is neutralised.', widget._csvCell('-1+2'), '"\'-1+2"');
same('Formula at-sign is neutralised.', widget._csvCell('@SUM(A1:A2)'), '"\'@SUM(A1:A2)"');
same('Space-prefixed formula is neutralised.', widget._csvCell(' =2+2'), '"\' =2+2"');
same('Tab-prefixed formula is neutralised.', widget._csvCell('\t=2+2'), '"\'\t=2+2"');

widget._searchText = ' user ';
widget._dateFrom = '2026-08-01';
widget._dateTo = '2026-08-25';
const request = widget.getUpdateRequestData();
same('Date is sent as a date string.', request.date_from, '2026-08-01');
same('No browser-local epoch conversion is used.', typeof request.date_from, 'string');

widget._searchText = '';
widget._dateFrom = '';
widget._dateTo = '';
widget._autoScrollEnabled = false;
widget._explicitUpdateRequested = false;
widget._stubUpdateCount = 0;
widget.promiseUpdate();
same('Auto-scroll off holds live view.', widget._stubUpdateCount, 0);

widget._explicitUpdateRequested = true;
widget.promiseUpdate();
same('One-shot live snapshot is allowed while held.', widget._stubUpdateCount, 1);

widget._searchText = '3076';
widget._explicitUpdateRequested = false;
widget.promiseUpdate();
same('Historical search does not poll automatically.', widget._stubUpdateCount, 1);

widget._explicitUpdateRequested = true;
widget.promiseUpdate();
same('Explicit historical search is allowed.', widget._stubUpdateCount, 2);

console.log('WidgetClientTest: all assertions passed.');
