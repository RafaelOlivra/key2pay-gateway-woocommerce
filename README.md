# Key2Pay WooCommerce Integration

A secure, production-ready WooCommerce payment gateway for Key2Pay, featuring robust authentication, secure redirect payment flow, and webhook support.

## ✅ Current Status

**Production Ready v1.0.1** – The plugin now includes:

- ✅ **Secure Redirect Payment Gateway**: Customers are redirected to Key2Pay’s hosted payment page for maximum security.
- ✅ **Admin Credential Fields**: Enter your Key2Pay Merchant ID and Password in the WooCommerce admin.
- ✅ **No Hardcoded Credentials**: All credentials are managed via the admin interface.
- ✅ **Webhook Handling**: Automatic order status updates via Key2Pay webhooks with comprehensive gateway response code handling.
- ✅ **Return URL Processing**: Handles payment results from both webhooks and return URL parameters.
- ✅ **Dynamic Currency Support**: Uses WooCommerce order currency.
- ✅ **Always English Language**: Payment page is always in English (`lang=en`).
- ✅ **Debug Logging**: Optional logging for troubleshooting.
- ✅ **WordPress Translation Ready**: Includes `.pot` and sample `.po` files.

## 🚀 Quick Setup

1. **Install the plugin** in your WordPress site.
2. **Go to WooCommerce → Settings → Payments**.
3. **Enable Key2Pay Secure Redirect**.
4. **Enter your Key2Pay Merchant ID and Password** in the gateway settings.
5. **Configure API Base URL** (sandbox or production).
6. **Select Payment Method Type** (Instapay, PHQR, or Credit Card).
7. **(Optional) Enable Debug Logging** for troubleshooting.
8. **Test with sandbox credentials** before going live.
9. **Enable the gateway** after testing is complete.

## ✅ Production Checklist

Before going live, ensure you have:

- [ ] **SSL Certificate** installed and working
- [ ] **Production API credentials** configured
- [ ] **Webhook URL** properly configured in Key2Pay dashboard
- [ ] **Debug logging disabled** (unless needed for troubleshooting)
- [ ] **URL parameter fallback disabled** (enabled by default for security)
- [ ] **Test transactions completed** successfully
- [ ] **Order status updates** working via webhooks
- [ ] **Error handling** tested with various scenarios

## 🔒 Security Features

- **No PCI DSS burden**: All card data is handled by Key2Pay.
- **Webhook signature verification** for secure callbacks.
- **Secure credential storage** in WooCommerce settings.
- **HTTPS enforcement** for all payment communications.

## 📊 Gateway Response Code Handling

The plugin now comprehensively handles all Key2Pay gateway response codes with **descriptive error messages**:

- **0**: **Approved** → Order marked as paid
- **51**: **Insufficient Funds** → Order marked as failed with user-friendly message
- **05**: **Do Not Honour** → Order marked as failed with user-friendly message
- **62**: **Restricted Card** → Order marked as failed with user-friendly message
- **12**: **Invalid Transaction** → Order marked as failed with user-friendly message
- **9998**: **Timeout** → Order marked as failed with user-friendly message
- **Other codes**: **Approved** → Order marked as paid (as per Key2Pay documentation)
- **CAPTURED**: **Legacy support** → Order marked as paid

**Note**: The plugin automatically handles currency-prefixed response codes (e.g., "EGP9998" → "9998", "USD51" → "51").

### 🎯 Enhanced Error Messages

The plugin now provides:
- **Technical messages** for admin order notes and logs
- **User-friendly messages** for customer display
- **Multi-language support** (English, Serbian, Japanese)
- **Specific guidance** for each error type

See [ERROR_MESSAGES.md](ERROR_MESSAGES.md) for complete details on the error message system.

### 🧪 Testing with Specific Amounts

For testing purposes, use these specific amounts to trigger different responses:
- **$5300** → Triggers approved response (gateway code 0)
- **$8851** → Triggers insufficient funds response (gateway code 51)
- **$105** → Triggers do not honour response (gateway code 05)
- **$162** → Triggers restricted card response (gateway code 62)
- **$112** → Triggers invalid transaction response (gateway code 12)
- **$9998** → Triggers timeout response (gateway code 9998)

## 📋 Requirements

- WordPress 5.0+
- WooCommerce 8.0+
- PHP 7.4+
- SSL certificate (for production)

## 🔧 Configuration

### API Base URLs

- **Sandbox**: `https://sandbox-api.key2payment.com/` (for testing)
- **Live**: `https://api.key2payment.com/` (for live payments)

The plugin automatically appends the required routes (e.g., `/PaymentToken/Create`) to the base URL.

### Payment Method Types

- **INSTAPAY**: For Instapay electronic transfers
- **PHQR**: For general hosted QR payments
- **CARD**: For credit card payments

## 🛠️ Development

- **Test Environment**: Use Key2Pay sandbox for development.
- **Debug Mode**: Enable logging for troubleshooting.
- **Webhook Testing**: Verify callback handling with test transactions.

## 🌐 Translations

- Translation ready (`.pot` file included).
- Example Serbian and Japanese translations provided.

## 📞 Support

For technical support or questions about Key2Pay integration, please refer to:
- [Authentication Documentation](AUTHENTICATION.md)
- WooCommerce logs for detailed error information
- Key2Pay's official API documentation

---