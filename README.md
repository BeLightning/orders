# WooCommerce Inquiry Form Plugin

WordPress/WooCommerce plugin that replaces the "Add to Cart" button with an inquiry form on all products.

## 🎯 Features

- ✅ Replaces "Add to Cart" button with inquiry form on **all products**
- ✅ Creates WooCommerce orders with custom "Inquiry" status
- ✅ Email notifications to admin only
- ✅ Supports simple and variable products
- ✅ AJAX form submission (no page reload)
- ✅ Quantity limit (max 10)
- ✅ Full Bulgarian localization

## 📋 Form Fields

- Full Name (required)
- Quantity (1-10, required)
- Email (required, validated)
- Phone (required) - 0 or +359 and min. 10 numbers

## 🚀 Installation

1. Upload the `orders` folder to `/wp-content/plugins/`
2. Activate the plugin through WordPress Admin → Plugins
3. Done! Forms will appear automatically on all products

## 📁 Structure

```
orders/
├── orders.php (main plugin file)
└── assets/
    ├── css/
    │   └── inquiry_css.css
    └── js/
        └── inquiry_js.js
```

## ⚙️ Requirements 
Tested and created on 5.1.2026
- WordPress 6.9
- WooCommerce 10.4
- PHP 8.2

## 📧 Email Notifications

Admin receives one email per inquiry with:
- Order number
- Product name and quantity
- Customer details (name, email, phone)
- Link to order in admin panel

## 🔧 Configuration

### Change Maximum Quantity

Edit `orders.php`:
```php
if ($quantity < 1 || $quantity > 10) { // Change 10 to your limit
```

### Change Admin Email

By default uses WordPress admin email. To change:
```php
$admin_email = get_option('admin_email'); // Replace with 'your@email.com'
```

## 📊 Managing Inquiries

1. Go to **WooCommerce → Orders**
2. Filter by status **"Запитване"** (Inquiry)
3. All inquiries have $0.00 total
4. Customer data saved in order meta fields

## 🔄 Optional: Enable Per-Product Toggle

The plugin includes commented code to enable/disable the form per product. To activate:
Uncomment

See inline comments marked with `==== PER-PRODUCT TOGGLE ====` or something like that

## 🛠️ Technical Details

### Custom Order Status
- Status slug: `wc-inquiry`
- Status label: "Запитване"
- Prevents stock reduction
- $0.00 order total

### Security
- Nonce verification
- Input sanitization
- Email validation
- AJAX authentication

### Email Prevention
- Disables all default WooCommerce emails for inquiry orders
- Transient protection against duplicate emails
- Only custom admin notification is sent

## 🐛 Troubleshooting

**Form not showing:**
- Clear cache (WordPress, browser, CDN)
- Deactivate and reactivate plugin
- Check WooCommerce is active

**Still see Add to Cart button:**
- Hard refresh (Ctrl+Shift+R)
- Check for plugin conflicts
- Check error log
- Try default theme (Storefront/Twenty Twenty-Five)

**Not receiving emails:**
- Check WordPress Settings → General → Email
- Install SMTP plugin (WP Mail SMTP)
- Check spam folder

## 📝 Changelog

### v1.0.0
- Initial release
- All products mode (no per-product toggle)
- Added transient protection for duplicate emails
- Code cleanup and optimization
- Full Bulgarian translation

### v1.0.1
- Per-product toggle functionality (see in comments)

## 👤 Author

Ivelina I.

## 📄 License

MIT License - see [LICENSE](LICENSE) file for details.

Copyright (c) 2026 Ivelina I.

Permission is hereby granted, free of charge, to any person obtaining a copy of this software and associated documentation files (the "Software"), to deal in the Software without restriction, including without limitation the rights to use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of the Software, and to permit persons to whom the Software is furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.

---

## 💡 Quick FAQ

**Q: Can I enable the form only on specific products?**  
A: Yes! Uncomment the per-product toggle code (see instructions above).

**Q: Do inquiries reduce stock?**  
A: No, inquiries have $0 price and don't affect inventory.

**Q: Does the customer receive an email?**  
A: No, only admin receives notifications.

**Q: Can I export inquiries?**  
A: Yes, use standard WooCommerce Order Export plugins.
