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

widget._searchText = ' user ';
widget._dateFrom = '2026-08-01';
widget._dateTo = '2026-08-25';
const request = widget.getUpdateRequestData();
same('Date is sent as a date string.', request.date_from, '2026-08-01');
same('No browser-local epoch conversion is used.', typeof request.date_from, 'string');

console.log('WidgetClientTest: all assertions passed.');
