# Development guide

## Coding principles

The project applies the following standards:

- one operational responsibility per component;
- strict PHP typing at file level;
- explicit return and parameter types where framework boundaries permit;
- deterministic fallback order;
- bounded external-data requests;
- no direct database access;
- comments explain rationale, invariants and constraints rather than restating syntax;
- raw source data is preserved when parsing is incomplete;
- browser-only operations remain non-destructive; history search remains server-side through the Zabbix API.

## PHP style

PHP code follows the established Zabbix module namespace and class layout while using strict types, final classes and typed private methods. Zabbix globals and constants are accessed only in the controller boundary.

## JavaScript style

The JavaScript class extends the Zabbix `CWidget` lifecycle. Transient state belongs to the widget instance so it survives normal dashboard refreshes without being persisted as monitoring configuration.

The implementation uses delegated detail-button handling because the complete widget DOM is replaced after each Zabbix refresh.

## CSS style

All selectors are scoped to the module. Design tokens are declared as custom properties on `.nps-wally`. Fixed table widths are deliberate: they create a stable scanning surface while allowing horizontal scrolling.

## Extending parsed fields

1. Add the exact English Windows label to `NpsEventParser`.
2. Define its precedence and empty-value behaviour.
3. Add it to the normalised return contract.
4. Add a parser fixture and assertion.
5. Add presentation only after the parser test passes.
6. Update `DATA_DICTIONARY.md`.

## Localisation

The user-interface strings use Zabbix's translation function. Windows event-message labels do not; they are source protocol text. Supporting non-English Windows Server installations requires a language-specific label map or collection of structured XML rather than superficial UI translation.

## Versioning

The project uses semantic versioning:

- patch: compatible correction or documentation change;
- minor: compatible feature or parser extension;
- major: incompatible dashboard configuration or data-contract change.
