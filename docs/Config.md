# Strichliste Config

The configuration can be found in `config/strichliste.yaml`. This is also reachable by the `/api/settings` endpoint and used by the frontend.

*Hint*: You can overwrite the import path in `config/services.yaml` if you want to put your config file into another place like `/etc/strichliste.yml`

*Hint*: You have to clear the cache with the corresponding command to apply config changes.

## common

| field        | datatype | default    | description          |
|--------------|----------|------------|----------------------|
| idleTimeout  | int      | 30000      | Time in milliseconds |

## paypal

You can enable the users to pay their debt or charge the account with paypal. All you need
is a valid paypal account.

| field      | datatype | default  | description                                    |
|------------|----------|----------|------------------------------------------------|
| enabled    | bool     | true     | Enable/Disable paypal feature                  |
| recipient  | string   | -        | Recipient Mail Address (paypal account)        |
| fee        | int      | 0        | Fee in percent (is added to the users balance) |


## user

| field       | datatype   | default | description                                       |
|-------------|------------|---------|---------------------------------------------------|
| stalePeriod | timeperiod | 10 day  | Determines, when a user is considered "inactive". |

## i18n

| field      | datatype | default    | description |
|------------|----------|------------|-------------|
| dateFormat | string   | YYYY-MM-DD | Date format |
| timezone   | string   | auto       | Timezone    |
| language   | string   | en         | Language    |

####  currency:

| field          | datatype | default | description                          |
|----------------|----------|---------|--------------------------------------|
| name           | string   | Euro    | Currency                             |
| symbol         | string   | €       | Currency symbol                      |
| alpha3         | string   | EUR     | ISO 4217 currency code               |
| fractionDigits | int      | 2       | Number of fractional digits (0 to 6) |

`fractionDigits` determines how persisted integer amounts are interpreted. Set it before recording monetary data and do not change it later: balances, transactions, and article prices have no independent precision metadata. Use `fractionDigits: 0` with a fresh database, or convert all persisted monetary values and related amount settings before changing precision.

PayPal requires a supported ISO currency code.

## account

#### boundary

| field | datatype | default | description         |
|-------|----------|---------|---------------------|
| upper | money    | 200000  | Upper account limit |
| lower | money    | -200000 | Lower account limit |

## payment

#### undo

The undo operation is a new feature from strichliste2, which allows to revert your last (accidental) transaction.

| field   | datatype   | default  | description                                         |
|---------|------------|----------|-----------------------------------------------------|
| enabled | bool       | true     | Enable/Disable undo feature                         |
| delete  | bool       | false    | Delete or mark transaction as deleted on undo       |
| timeout | timeperiod | 5 minute | Period how long you're able to undo the transaction |

####  boundary

You can limit your transaction to prevent accidental payments (like adding a unwanted zero).

| field | datatype | default | description             |
|-------|----------|---------|-------------------------|
| upper | money    | 15000   | Upper transaction limit |
| lower | money    | -2000   | Lower transaction limit |

#### transactions

A new strichliste2 feature is transferring money to other accounts with an optional comment.

| field   | datatype | default | description                  |
|---------|----------|---------|------------------------------|
| enabled | bool     | true    | Enable/Disable sending money |

#### deposit

| field   | datatype | default | description                             |
|---------|----------|---------|-----------------------------------------|
| enabled | bool     | true    | Enable/Disable quick money pay in       |
| custom  | book     | true    | Enable/Disable deposit custom amounts   |
| steps   | money[]  |         | Available payment steps in the configured currency's smallest unit |

#### dispense

| field   | datatype | default | description                             |
|---------|----------|---------|-----------------------------------------|
| enabled | bool     | true    | Enable/Disable quick expend             |
| custom  | book     | true    | Enable/Disable dispense custom amounts  |
| steps   | money[]  |         | Available payment steps in the configured currency's smallest unit |

## Datatypes

#### timeperiod

For format see: https://www.php.net/manual/en/datetime.formats.php#datetime.formats.relative

#### money

Configured amounts and API amount fields use integer minor units. `i18n.currency.fractionDigits` defines the conversion: with the default value of `2`, €10 is 1000 minor units; with `0`, 10 means 10 units. Account and payment boundaries and quick-payment steps use the same integer unit.
