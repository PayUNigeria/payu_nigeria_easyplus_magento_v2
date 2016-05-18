### Magento 2 PayU extension installation guide ###

This installation guide makes the following assumptions

1. You know how to use a web browser.
2. You have installed Magento 2 and it’s working properly.

With that out of the way let’s get to the meat of this guide.

There are several ways to install a module/extension in Magento 2

1. Using composer
2. Using file transfer
3. Using component manager as well [Run the Component Manager] (http://devdocs.magento.com/guides/v2.0/comp-mgr/compman-start.html)

For using composer installation Please find the complete link explained by Alan kent , Chief Magento Architect [Creating a Magento 2 Composer Module] (https://alankent.me/2014/08/03/creating-a-magento-2-composer-module/)

### Using File Transfer ###

1. Extract the archive source to anywhere on your computer and remember the location
2. You need to move “app” folder of the extracted archive to your Magento 2 installation matching the “app” folder structure as per the screenshot. This can be achieved with an FTP client such as FileZilla
3. Run the command in your Magento 2 root directory to install the module “php bin/magento module:enable PayU_EasyPlus--clear-static-content”
4. After the successful installation you have to run the command on Magento2 root directory. “php bin/magento setup:upgrade”
![root1](https://cloud.githubusercontent.com/assets/5717025/15351203/6e689966-1cde-11e6-8c4b-2d3fd070e546.png)
5. Also, run this commands in the Magento2 Root. You can refer the below screenshot.
![root2](https://cloud.githubusercontent.com/assets/5717025/15351229/90c33138-1cde-11e6-9567-043855351528.png)
6. After running the above command, run this also to clear Magento cache.
“php bin/magento cache:clean”

To continue with module configuration jump to step 4 of “Using Component Manager” installation method.

### Using Component Manager ###

This installation method is aim at those without access to a Command Line Interface (CLI). After manually uploading the files follow these steps to install the module/extension.

1. Navigate to System -> Web Setup Wizard and click “Component Manager” to launch the module installation process.
![composer1](https://cloud.githubusercontent.com/assets/5717025/15351263/c539830e-1cde-11e6-897d-cbcfb4c84ed0.png)
2. Click to select “Enable”
![composer2](https://cloud.githubusercontent.com/assets/5717025/15351283/e3efb016-1cde-11e6-96fc-ab17ea639668.png)
3. Resolve all issues before proceeding to Next. You cannot proceed if Issues are not resolved. Clicking next would allow you do a back before module installation proceeds. It’s always recommended to do a backup before installing modules in Magento 2.
![composer3](https://cloud.githubusercontent.com/assets/5717025/15351312/01ccb7f0-1cdf-11e6-92a7-7ec2c3b60981.png)
4. After Installation navigate to Stores -> Configuration -> Sales -> Payment Methods. You should find PayU Easy and Business Merchants payment method listed among the other payment methods. Save your configuration changes and clear the Cache for your changes to take effect.

NOTE: You must configure the payment methods using the safe key, API username, API password details provided by PayU when you open an account.
If in doubt leave the default values unchanged. Also switch to live server mode before going live.
![composer4](https://cloud.githubusercontent.com/assets/5717025/15351334/26d53c2a-1cdf-11e6-9769-24ff15b1119d.png)
5. PayU would now be available for customers to make payment.
![composer5](https://cloud.githubusercontent.com/assets/5717025/15351365/48152710-1cdf-11e6-9c68-8d8678f14797.png)
6. Redirecting to PayU for payment processing after customer places an order.
![composer6](https://cloud.githubusercontent.com/assets/5717025/15351410/7c3a2464-1cdf-11e6-8ce1-eb2b2ece206e.png)
