# API Result Codes

This document lists the **ResultCode** values returned by Safaricom's M-Pesa Daraja APIs and their meanings. Each API can return a subset of these codes; the tables below show the most common ones for each endpoint.

> [!NOTE]
> For a complete and up-to-date list of all possible result codes, please refer to the Daraja API documentation [Safaricom Developer Portal](https://daraja.safaricom.co.ke/Documentation).

---

## 1. STK Push (Lipa Na M-Pesa Online)

| ResultCode | Meaning            | Description                                                      |
| :--------- | :----------------- | :--------------------------------------------------------------- |
| `0`        | **Success**        | The service request is processed successfully.                   |
| `1`        | Insufficient Funds | The customer does not have enough money in their M-PESA account. |
| `1032`     | Cancelled          | Request cancelled by user.                                       |
| `1037`     | Timeout            | DS timeout user cannot be reached (phone off, busy, etc).        |
| `2001`     | Invalid PIN        | The customer entered an incorrect M-PESA PIN.                    |
| `1019`     | Expired            | The transaction was not processed within the allowable time.     |
| `1025`     | Error              | An error occurred while sending a push request.                  |

---

## 2. STK Push Query

| ResultCode | Meaning     | Description                                      |
| :--------- | :---------- | :----------------------------------------------- |
| `0`        | **Success** | Transaction completed successfully.              |
| `1032`     | Cancelled   | The original STK Push was cancelled by the user. |
| `1037`     | Timeout     | The original STK Push timed out.                 |

---

## 3. C2B (Customer to Business)

| ResultCode | Meaning     | Description                                       |
| :--------- | :---------- | :------------------------------------------------ |
| `0`        | **Success** | URL registration or simulation succeeded.         |
| `1`        | Rejected    | The validation URL returned a rejection response. |

---

## 4. B2C (Business to Customer)

| ResultCode | Meaning                     | Description                                    |
| :--------- | :-------------------------- | :--------------------------------------------- |
| `0`        | **Success**                 | B2C request accepted.                          |
| `2001`     | Invalid Initiator           | The initiator information is invalid.          |
| `2002`     | Invalid Security Credential | The security credential is invalid or expired. |
| `1001`     | Invalid Account             | The account being debited is invalid.          |

---

## 5. B2B (Business to Business)

| ResultCode | Meaning           | Description                           |
| :--------- | :---------------- | :------------------------------------ |
| `0`        | **Success**       | B2B request accepted.                 |
| `2001`     | Invalid Initiator | The initiator information is invalid. |

---

## 6. Transaction Status

| ResultCode | Meaning     | Description                                    |
| :--------- | :---------- | :--------------------------------------------- |
| `0`        | **Success** | Transaction status retrieved successfully.     |
| `2001`     | Not Found   | No transaction matches the provided reference. |

---

## 7. Account Balance

| ResultCode | Meaning     | Description                     |
| :--------- | :---------- | :------------------------------ |
| `0`        | **Success** | Balance retrieved successfully. |

---

## 8. Reversal

| ResultCode | Meaning                | Description                               |
| :--------- | :--------------------- | :---------------------------------------- |
| `0`        | **Success**            | Reversal request accepted.                |
| `2001`     | Invalid Transaction ID | Original transaction reference not found. |

---

## 9. Dynamic QR Code

| ResultCode | Meaning     | Description                     |
| :--------- | :---------- | :------------------------------ |
| `0`        | **Success** | QR code generated successfully. |

---

## 10. Pull Transaction

| ResultCode | Meaning     | Description                           |
| :--------- | :---------- | :------------------------------------ |
| `0`        | **Success** | Transaction data pulled successfully. |

---

_Documentation updated for **Laravel M-Pesa v0.2.0**_
