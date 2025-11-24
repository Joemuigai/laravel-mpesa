# API Result Codes

This document lists the **ResultCode** values returned by Safaricom's M-Pesa Daraja APIs and their meanings. Each API can return a subset of these codes; the tables below show the most common ones for each endpoint.

---

## 1. STK Push (Lipa Na M-Pesa Online)

| ResultCode   | Meaning                                                                  | Description                                                                                                                                                                                                                                                                                                |
| ------------ | ------------------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `0`          | The service request is processed successfully.                           | The transaction has been processed successfully on M-PESA.                                                                                                                                                                                                                                                 |
| `1`          | The balance is insufficient for the transaction.                         | The customer does not have enough money in their M-PESA account to complete the transaction.                                                                                                                                                                                                               |
| `2`          | Declined due to limit rule.                                              | The amount provided is less than the allowed C2B transaction minimum (currently Ksh 1).                                                                                                                                                                                                                    |
| `3`          | Declined due to limit rule: greater than the maximum transaction amount. | The amount provided exceeds the allowed C2B transaction maximum.                                                                                                                                                                                                                                           |
| `4`          | Declined due to limit rule: would exceed daily transfer limit.           | The transaction would exceed the customer's daily transfer limit (currently Ksh 500,000).                                                                                                                                                                                                                  |
| `8`          | Declined due to limit rule: would exceed the maximum balance.            | Processing the transaction would exceed the Pay Bill or Till Number account balance limit.                                                                                                                                                                                                                 |
| `17`         | Rule limited.                                                            | Transactions were initiated in succession (within 2 minutes) for the same amount to the same customer. Wait at least 2 minutes between such requests.                                                                                                                                                      |
| `1019`       | Transaction has expired.                                                 | The transaction was not processed within the allowable time.                                                                                                                                                                                                                                               |
| `1025`       | An error occurred while sending a push request.                          | The USSD prompt message is too long (over 182 characters). Ensure the account reference value is not too long.                                                                                                                                                                                             |
| `1032`       | Request cancelled by user.                                               | The prompt was canceled by the user.                                                                                                                                                                                                                                                                       |
| `1037`       | DS timeout user cannot be reached.                                       | The customer's phone could not be reached with the NIPUSH prompt (phone offline, busy, or ongoing session).                                                                                                                                                                                                |
| `2001`       | The initiator information is invalid.                                    | The customer entered an incorrect M-PESA PIN. Advise the customer to use the correct PIN.                                                                                                                                                                                                                  |
| `2028`       | The request is not permitted according to product assignment.            | Either the TransactionType or PartyB is incorrect. For BuyGoods/Tills:- BusinessShortCode: short code used on Go Live- PartyB: Till Number- TransactionType: CustomerBuyGoodsOnlineFor Pay Bills:- BusinessShortCode: short code used on Go Live- PartyB: Pay Bill- TransactionType: CustomerPayBillOnline |
| `8006`       | The security credential is locked.                                       | The customer should contact Customer Care (call 100 or 200) for assistance.                                                                                                                                                                                                                                |
| `SFC_IC0003` | The operator does not exist.                                             | Either the TransactionType or PartyB is incorrect. For BuyGoods/Tills:- BusinessShortCode: short code used on Go Live- PartyB: Till Number- TransactionType: CustomerBuyGoodsOnlineFor Pay Bills:- BusinessShortCode: short code used on Go Live- PartyB: Pay Bill- TransactionType: CustomerPayBillOnline |

---

## 2. STK Push Query

| ResultCode | Meaning             | Description                                                  |
| ---------- | ------------------- | ------------------------------------------------------------ |
| `0`        | Success             | Transaction completed successfully.                          |

---

## 3. C2B (Customer to Business) – Register URL & Simulate

| ResultCode | Meaning                | Description                                              |
| ---------- | ---------------------- | -------------------------------------------------------- |
| `0`        | Success                | URL registration or simulation succeeded.                |
| `2001`     | Invalid URL            | The callback URL provided is not reachable or malformed. |
| `2002`     | Validation Failed      | The request payload failed validation.                   |
| `2003`     | Duplicate Registration | The URL has already been registered.                     |

---

## 4. B2C (Business to Customer)

| ResultCode | Meaning              | Description                                  |
| ---------- | -------------------- | -------------------------------------------- |
| `0`        | Success              | B2C request accepted.                        |
| `1001`     | Invalid Initiator    | Initiator name is missing or incorrect.      |
| `1002`     | Invalid Command ID   | Command ID is not recognized.                |
| `1003`     | Invalid Amount       | Amount is missing or not a positive number.  |
| `1004`     | Invalid Party B      | Destination phone number is invalid.         |
| `1005`     | Insufficient Balance | Merchant account lacks sufficient balance.   |
| `2001`     | Timeout              | Request timed out.                           |
| `2002`     | System Busy          | Safaricom system is temporarily unavailable. |

---

## 5. B2B (Business to Business)

| ResultCode | Meaning              | Description                                  |
| ---------- | -------------------- | -------------------------------------------- |
| `0`        | Success              | B2B request accepted.                        |
| `1001`     | Invalid Initiator    | Initiator name is missing or incorrect.      |
| `1002`     | Invalid Command ID   | Command ID is not recognized.                |
| `1003`     | Invalid Amount       | Amount is missing or not a positive number.  |
| `1004`     | Invalid Party B      | Destination business shortcode is invalid.   |
| `1005`     | Insufficient Balance | Merchant account lacks sufficient balance.   |
| `2001`     | Timeout              | Request timed out.                           |
| `2002`     | System Busy          | Safaricom system is temporarily unavailable. |

---

## 6. Transaction Status

| ResultCode | Meaning               | Description                                    |
| ---------- | --------------------- | ---------------------------------------------- |
| `0`        | Success               | Transaction status retrieved successfully.     |
| `2001`     | Transaction Not Found | No transaction matches the provided reference. |
| `2002`     | Invalid Request       | Request payload is malformed.                  |
| `2003`     | System Error          | Unexpected error on Safaricom side.            |

---

## 7. Account Balance

| ResultCode | Meaning           | Description                              |
| ---------- | ----------------- | ---------------------------------------- |
| `0`        | Success           | Balance retrieved successfully.          |
| `1001`     | Invalid Initiator | Initiator name missing or incorrect.     |
| `2001`     | System Error      | Unexpected error while fetching balance. |

---

## 8. Reversal

| ResultCode | Meaning                | Description                                       |
| ---------- | ---------------------- | ------------------------------------------------- |
| `0`        | Success                | Reversal request accepted.                        |
| `1001`     | Invalid Initiator      | Initiator name missing or incorrect.              |
| `1002`     | Invalid Transaction ID | Original transaction reference not found.         |
| `2001`     | Insufficient Funds     | Merchant does not have enough balance to reverse. |
| `2002`     | System Busy            | Safaricom system temporarily unavailable.         |

---

## 9. Dynamic QR Code

| ResultCode | Meaning           | Description                                |
| ---------- | ----------------- | ------------------------------------------ |
| `0`        | Success           | QR code generated successfully.            |
| `1001`     | Invalid Shortcode | Shortcode does not belong to the merchant. |
| `2001`     | System Error      | Unexpected error while generating QR.      |

---

## 10. Pull Transaction

| ResultCode | Meaning            | Description                                 |
| ---------- | ------------------ | ------------------------------------------- |
| `0`        | Success            | Transaction data pulled successfully.       |
| `2001`     | No Transactions    | No transactions found for the given period. |
| `2002`     | Invalid Parameters | Query parameters are malformed.             |
| `2003`     | System Error       | Unexpected error on Safaricom side.         |

---

### Notes

-   **ResultCode `0`** always indicates a successful request.
-   Non‑zero codes are **error or status** codes; consult the official Safaricom Daraja documentation for the full list of possible values.
-   The package maps these codes to exceptions where appropriate, allowing you to handle them via try‑catch blocks.

---

_Generated by_ **Joemuigai**.
