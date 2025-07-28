# Key2Pay Authentication Methods

This plugin supports multiple authentication methods for the Key2Pay API. Choose the method that matches your Key2Pay account configuration.

## Available Authentication Methods

### 1. Basic Authentication (Default)
- **Method**: Merchant ID + Password in request body
- **Required Fields**: Merchant ID, Password
- **Security Level**: Basic
- **Use Case**: Legacy Key2Pay accounts or simple integrations

### 2. API Key Authentication
- **Method**: API Key in request header (`X-API-Key`)
- **Required Fields**: API Key
- **Security Level**: Medium
- **Use Case**: Modern Key2Pay accounts with API key access

### 3. Bearer Token Authentication
- **Method**: Access Token in Authorization header (`Bearer <token>`)
- **Required Fields**: Access Token
- **Security Level**: High
- **Use Case**: OAuth-based authentication or token-based access

### 4. HMAC Signed Authentication
- **Method**: API Key in header + HMAC signature in request body
- **Required Fields**: API Key, Secret Key
- **Security Level**: Very High
- **Use Case**: Maximum security with request signing and webhook verification

## Configuration

### WooCommerce Admin Setup
1. Go to **WooCommerce → Settings → Payments**
2. Find **Key2Pay InstaPay** and click **Manage**
3. Select your **Authentication Method**
4. Fill in the required credentials for your chosen method
5. Save changes

### Required Fields by Authentication Method

| Method | Merchant ID | Password | API Key | Secret Key | Access Token |
|--------|-------------|----------|---------|------------|--------------|
| Basic | ✅ | ✅ | ❌ | ❌ | ❌ |
| API Key | ❌ | ❌ | ✅ | ❌ | ❌ |
| Bearer | ❌ | ❌ | ❌ | ❌ | ✅ |
| HMAC Signed | ❌ | ❌ | ✅ | ✅ | ❌ |

## Security Recommendations

### For Production Use
1. **Use HMAC Signed** authentication when possible
2. **Enable webhook signature verification** with your Secret Key
3. **Use HTTPS** for all API communications
4. **Store credentials securely** and never expose them in logs
5. **Rotate credentials regularly** as per Key2Pay's security policies

### Webhook Security
- Configure webhook signature verification using your Secret Key
- Verify webhook source IP addresses if Key2Pay provides them
- Always validate webhook payloads before processing

## Testing

### Sandbox Environment
- Use Key2Pay's sandbox/test environment for development
- Test all authentication methods before going live
- Verify webhook handling with test transactions

### Debug Mode
- Enable debug logging to troubleshoot authentication issues
- Check WooCommerce logs for detailed error messages
- Monitor API request/response data during testing

## Troubleshooting

### Common Issues

1. **"Authentication not properly configured"**
   - Check that all required fields for your chosen method are filled
   - Verify credentials are correct and not expired

2. **"API request failed"**
   - Check network connectivity to Key2Pay servers
   - Verify API endpoints are correct
   - Review debug logs for specific error messages

3. **"Webhook verification failed"**
   - Ensure Secret Key is correctly configured
   - Check webhook signature header format
   - Verify webhook payload format

### Getting Help
- Check Key2Pay's official API documentation
- Review WooCommerce logs for detailed error information
- Contact Key2Pay support for API-specific issues 