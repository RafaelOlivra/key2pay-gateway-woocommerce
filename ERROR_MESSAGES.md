# Key2Pay Error Message System

This document explains the comprehensive error message system implemented in the Key2Pay WooCommerce plugin, which provides descriptive and user-friendly error messages for each gateway response code.

## Overview

The plugin now includes separate translation strings for each status code, providing:
- **Technical messages** for admin/order notes
- **User-friendly messages** for customer display
- **Multi-language support** for all error messages

## Gateway Response Codes

The plugin handles the following Key2Pay gateway response codes with specific error messages:

### ✅ Approved Transactions
- **Code**: `0` (or any amount ending in `00`)
- **Technical Message**: "Payment approved successfully."
- **User Message**: "Your payment has been approved successfully!"

### ❌ Failed Transactions

#### Insufficient Funds
- **Code**: `51` (or any amount ending in `51`)
- **Technical Message**: "Payment failed: Insufficient funds in the account."
- **User Message**: "Sorry, your payment could not be processed due to insufficient funds. Please check your account balance and try again."

#### Do Not Honour
- **Code**: `05` (or any amount ending in `05`)
- **Technical Message**: "Payment failed: Do not honour - the transaction was declined by the bank."
- **User Message**: "Sorry, your payment was declined by your bank. Please contact your bank or try a different payment method."

#### Restricted Card
- **Code**: `62` (or any amount ending in `62`)
- **Technical Message**: "Payment failed: Restricted card - this card cannot be used for this transaction."
- **User Message**: "Sorry, this card cannot be used for this transaction. Please try a different card or contact your bank."

#### Invalid Transaction
- **Code**: `12` (or any amount ending in `12`)
- **Technical Message**: "Payment failed: Invalid transaction - the transaction details are not valid."
- **User Message**: "Sorry, there was an issue with the transaction details. Please check your information and try again."

#### Timeout
- **Code**: `9998` (specific amount only)
- **Technical Message**: "Payment failed: Transaction timeout - the request took too long to process."
- **User Message**: "Sorry, the payment request timed out. Please try again or contact support if the problem persists."

#### Unknown Codes
- **Code**: Any other code not in the above list
- **Technical Message**: "Payment processed with unknown response code."
- **User Message**: "Sorry, there was an unexpected issue with your payment. Please try again or contact support."

## Implementation Details

### Methods Added

1. **`get_status_code_message($code)`**
   - Returns technical/descriptive messages for admin use
   - Used in order notes and admin logs

2. **`get_user_friendly_error_message($code)`**
   - Returns customer-friendly messages
   - Used for customer-facing error displays

3. **`get_user_friendly_error_message_for_failed_order($order)`**
   - Analyzes order notes to find specific error codes
   - Returns appropriate user-friendly message for failed orders

### Usage Examples

#### In Order Processing
```php
// Get descriptive message for order notes
$status_message = $this->get_status_code_message($numeric_code);
$order->add_order_note(sprintf(
    __('Key2Pay credit card payment approved. Transaction ID: %s, Code: %s - %s', 'key2pay'),
    $transaction_id, 
    $numeric_code, 
    $status_message
));
```

#### In Customer Display
```php
// Get user-friendly message for customers
$failed_message = $this->get_user_friendly_error_message_for_failed_order($order);
echo wpautop(wp_kses_post($failed_message));
```

## Translation Support

All error messages are fully translatable using WordPress's translation system:

### Available Languages
- **English** (default)
- **Serbian** (`sr_RS`)
- **Japanese** (`ja`)

### Translation Files
- `key2pay.pot` - Template file with all translatable strings
- `key2pay-sr_RS.po` - Serbian translations
- `key2pay-ja.po` - Japanese translations

### Adding New Languages
1. Copy `key2pay.pot` to `key2pay-[locale].po`
2. Translate the strings in the `.po` file
3. Compile to `.mo` file using a translation tool

## Testing Error Messages

### Test Amounts
Use these specific amounts to test different error responses:

- **$5300** → Approved (code 0)
- **$8851** → Insufficient funds (code 51)
- **$105** → Do not honour (code 05)
- **$162** → Restricted card (code 62)
- **$112** → Invalid transaction (code 12)
- **$9998** → Timeout (code 9998)

### Currency Prefix Handling
The system automatically handles currency-prefixed response codes:
- `EGP9998` → `9998`
- `USD51` → `51`
- `9998` → `9998` (no prefix)

## Benefits

1. **Better User Experience**: Customers receive clear, actionable error messages
2. **Improved Support**: Admins get detailed technical information in order notes
3. **Multi-language Support**: Error messages are available in multiple languages
4. **Consistent Messaging**: Standardized error messages across all payment scenarios
5. **Easy Maintenance**: Centralized error message management

## Future Enhancements

Potential improvements for future versions:
- Add more specific error codes as needed
- Implement error message customization in admin settings
- Add error message analytics and reporting
- Support for dynamic error messages based on transaction context 