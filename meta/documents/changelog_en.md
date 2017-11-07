# Release Notes for PayPal

## 1.2.1 (2017-10-19)

### Fixed

- Refund performs correctly now
- Additional payment data is retrieved from PayPal and saved in plentymarkets


## 1.2.0 (2017-10-11)

### Added

- PayPal Express now shows an order review page before the finalisation of the order

## 1.1.4 (2017-10-09)

### Fixed

- set paypal express as payment method

## 1.1.3 (2017-10-04)

### Fixed

- Additional payment data are retrieved from PayPal and saved in plentymarkets.

## 1.1.2 (2017-09-25)

### Fixed

- PayPal Wall is re-rendered when certain events are triggered in the checkout.

## 1.1.1 (2017-09-20)

### Fixed

- Error while loading financing costs.
- Use correct house number for streets with blank spaces.

## 1.1.0 (2017-09-01)

### Added

- Settings for **Info page** were added.
- Settings for **Description** were added.
- A method was added to determine if a customer can switch from this payment method to another payment method.
- A method was added to determine if a customer can switch to this payment method from another payment method.
- An option to reinitialise a payment from the **My account** area (PayPal/PayPal PLUS) was added.
- An option to reinitialise a payment on the order confirmation page (PayPal/PayPal PLUS) was added.
- The icon of the payment method has been added and can be displayed in the online store, e.g. on the homepage of **Ceres**.

### Changed

- Removed surcharges for the payment method.

### Fixed

- In case the payment is canceled, the customer is redirected to the checkout instead of the homepage of the shop.

### TODO

- Under **PayPal Scripts**, the link has to be changed from **Script loader: Register/load JS** to **Script loader: After scripts loaded**.

## 1.0.7 (2017-08-04)

### Fixed
- PayPal Plus Wall works without third party payment methods.

## 1.0.6 (2017-07-03)

### Fixed
- Correct image path for the PayPal PLUS wall.

## 1.0.5

### Fixed
- Correct PayPal PLUS BN Code.

## 1.0.4

### Fixed
- Live mode fallback 

### Added
- Documentation: "Receiving access data from PayPal".

## 1.0.3

### Fixed
- Fix saving and deleting accounts.

## 1.0.2

### Fixed
- Use the correct path for the PayPal logo
- Correctly create the hash for the unique identity of the payment
- Refund uses the correct order as basis

## 1.0.1

### Fixed
- Use the Invoice data from PayPal PLUS for the invoice
- Use correct company data for the installment overlay
- Fix saving account settings

## 1.0.0

### Added
- Add Installments Powered by PayPal
- PayPal PLUS Wall used other layout container
- Use the Event procedure also for Installments Powered by PayPal and PayPal PLUS

## 0.7.2

### Fixed
- PayPal PLUS Wall: Display of external payment methods

## 0.7.1

### Added
- Several adjustments in the plugin.json
- Authentification of „settings“ routes

### Fixed
- Account settings: Environment is now saved correctly

## 0.7.0

### Features
  
- **PayPal for plentymarkets**
- **PayPal PLUS for plentymarkets**
