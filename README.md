[![Packagist Version](https://img.shields.io/packagist/v/solution25/maxmind.svg)](https://packagist.org/packages/solution25/maxmind)
[![Packagist Downloads](https://img.shields.io/packagist/dt/solution25/maxmind.svg)](https://packagist.org/packages/solution25/maxmind)
[![License: MIT](https://img.shields.io/badge/license-MIT-green.svg)](https://github.com/solution25com/maxmind-fraud-prevention-shopware-6-solution25/blob/main/LICENSE.md)

![1](https://github.com/user-attachments/assets/d37c88d5-0bdd-4ca9-ba14-9d2a26cc988a)

# MaxMind Fraud Prevention

## Introduction

The MaxMind plugin helps detect and prevent fraud in Shopware stores. It checks every order for fraud risk using MaxMind’s service and assigns a risk score. If the score is too high, the order is flagged for review.
The plugin helps store owners reduce fraud by automatically analyzing orders, flagging suspicious ones, and providing risk scores in the admin panel.

### Key Features

1. **Fraud Detection**
   - Analyzes orders using MaxMind and assigns a risk score.
2. **Automatic Order Review**
   - Flags orders as “Fraud Review” if the risk score is too high.
3. **Admin Panel Integration**
   - Allows configuration of API keys, risk thresholds, and settings.
4. **Device Tracking**
   - Injects MaxMind’s JavaScript for fraud detection.
5. **Easy Monitoring**
   - Displays fraud scores in the Orders grid and Order Detail view.
6. **Shopware Compatibility**
   - Works with Shopware 6.4–6.5 and future updates.
  
## Compatibility
- ✅ Shopware 6.6.x 

## Get Started

### Installation & Activation

1. **Download**

## Git

- Clone the Plugin Repository:
- Open your terminal and run the following command in your Shopware 6 custom plugins directory (usually located at custom/plugins/):
  ```
  git clone https://github.com/solution25com/maxmind-fraud-prevention-shopware-6-solution25.git
  ```

## Packagist
 ```
  composer require solution25/maxmind
  ```

2. **Install the Plugin in Shopware 6**

- Log in to your Shopware 6 Administration panel.
- Navigate to Extensions > My Extensions.
- Locate the newly cloned plugin and click Install.

3. **Activate the Plugin**

- After installation, click Activate to enable the plugin.
- In your Shopware Admin, go to Settings > System > Plugins.
- Upload or install the “MaxMind” plugin.
- Once installed, toggle the plugin to activate it.

4. **Verify Installation**

- After activation, you will see MaxMind in the list of installed plugins.
- The plugin name, version, and installation date should appear as shown in the screenshot below.
![2](https://github.com/user-attachments/assets/3052ef31-11ef-4cb3-ad68-d483343a8412)

## Plugin Configuration

1. **Access Plugin Settings**

- Go to Settings > System > Plugins.
- Locate MaxMind and click the three dots (...) icon or the plugin name to open its settings.

2. **General Settings**

- **Sales Channel**
  - Select the sales channel(s) where you want MaxMind to be active. If you choose “All Sales Channels,” it will apply to every channel in your store.
- **MaxMind Account ID**
  - Enter the Account ID provided by MaxMind.
- **MaxMind License Key**
  - Enter the License Key from your MaxMind account.
- **Risk Threshold (0 to 99)**
  - If an order’s risk score exceeds this threshold, the order status is set to Fraud Review.

3. **Save Configuration**

- Click Save in the top-right corner to store your settings.

![3](https://github.com/user-attachments/assets/bc5adf85-f999-4ea8-bf1f-11bb32699b25)

## How It Works

1. **Customer Places an Order**

- When the customer checks out, the plugin sends order data to MaxMind’s API for a fraud assessment.

2. **Risk Score Calculation**

- MaxMind returns a Fraud Risk Score (0.01 to 99).
- If the score is above your configured threshold, the plugin automatically sets the order status to Fraud Review.
- If the score is below the threshold, the order is automatically marked as Fraud Pass.
- The Open and Cancel statuses are no longer used in this workflow.

3. **Order Status Update**

- The Order status field in Shopware will show “Fraud Review” if the risk score exceeds your threshold.
- You can see this status in the Orders overview page.

## Viewing and Managing Orders

1. **Navigate to Orders**

- In the Shopware Admin, click Orders.
- You will see a list of all orders with columns for Order status, Payment status, Delivery status, and Fraud Risk Score.

2. **Review Fraud Risk Score**

- Look at the Fraud Risk Score (%) column.
- Orders with a risk score higher than your threshold will appear as Fraud Review in the Order status column.
- Orders with a risk score lower than your threshold will automatically be marked as Fraud Pass.
- The Open and Cancel statuses are no longer used in this workflow.

3. **Manually Changing Order Status**

- Click on an order to open its detail page.
- In the General tab, you can change the Order status from Fraud Review to Fraud Pass or Fraud Fail after reviewing the order details.

4. **Orders Overview with Fraud Review & Fraud Risk Score**
![4](https://github.com/user-attachments/assets/b9e10a68-1737-48f7-ac44-df56c0ce080f)

5. **Order Detail Page with Status Options**
![5](https://github.com/user-attachments/assets/aff82a0e-b262-492d-9b2a-d3464da14454)

---

# MaxMind Plugin - API Documentation
 
This documentation describes the custom Admin API endpoint provided by the MaxMind Plugin for Shopware 6.  
The plugin integrates MaxMind’s fraud detection service to assess the risk level of orders.  
The endpoint allows retrieval of the fraud risk score for a specific order, helping merchants identify and manage potentially fraudulent transactions.
 
---
 
## Get MaxMind Fraud Details for an Order
 
**Endpoint**  
`GET /api/_action/maxmind/fraud-details/{orderId}`
 
### Description
 
Retrieves the MaxMind fraud risk score associated with a specific order using its Shopware Order ID.
 
### Request Headers
 
```
Authorization: Bearer <admin-api-token>
Content-Type: application/json
```
 
### Example Request
 
```
GET /api/_action/maxmind/fraud-details/5b6a139e54e54ed7b7997c71f6f56f9e
```
 
### Successful Response
 
```json
{
  "orderId": "5b6a139e54e54ed7b7997c71f6f56f9e",
  "fraudRisk": "low"
}
```
 
### Example Error Response
 
```json
{
  "error": "Order not found"
}
```
 
---
 
## Authentication
 
All endpoints require a valid Admin API Bearer token.  
You can obtain this via the standard Shopware Admin API authentication process.

## Best Practices

- **Set a Reasonable Threshold**
  - Too low (e.g., 0.1) may flag many legitimate orders.
  - Too high (e.g., 99) may miss fraudulent ones.
- **Monitor Flagged Orders**
  - Always manually review orders marked as “Fraud Review.”
  - Look for suspicious details like mismatched addresses or unusual email domains.
- **Keep Credentials Up to Date**
  - Ensure your MaxMind Account ID and License Key are valid.
  - An expired key will stop risk scores from being retrieved.
- **Stay Current with Plugin Updates**
  - Keep the plugin updated to ensure compatibility with the latest Shopware and MaxMind API changes.

## Troubleshooting

- **No Risk Scores Appearing**
  - Double-check your MaxMind credentials (Account ID and License Key).
  - Ensure the plugin is enabled for the correct Sales Channel.
- **Orders Not Changing Status**
  - Verify that your Risk Threshold is properly set.
  - Check for conflicts with other order management plugins.
- **Settings Not Saving**
  - Clear Shopware’s cache after saving.
  - Check file permissions if changes don’t persist.
 
## FAQ
- **Is a MaxMind account required?** 
   - Yes. You need an active MaxMind account and a valid license key for the plugin to function. 
- **Can I limit the plugin to specific sales channels?**
   - Yes. In the plugin settings, you can select which channels it should apply to. 
- **What happens to orders flagged as ‘Fraud Review’?**
   - You can investigate them and then manually change their status to Fraud Pass or Fraud Fail as needed. 
- **Does the plugin handle refunds or chargebacks automatically?**
   - No. It only provides a fraud risk score and sets the order status. Refunds/chargebacks must be managed separately. 

## Wiki Documentation
Read more about the plugin configuration on our [Wiki](https://github.com/solution25com/maxmind-fraud-prevention-shopware-6-solution25/wiki).


