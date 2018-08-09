# Authenticator
A web service that gives you safe access to your passwords.

## How it works
Well... I don't remembers exactly to be honnest. Basically the `Authenticator` class does everything but you might read the code to properly understand how everything works... sorry about that.

1. Create a `data_raw` file at the root of the project and fill it with your passwords. Format is `Name of service: password`, one entry per line. For example: `Gmail: mySuperStrongPassword`.
2. Call the `encryptData()` method after you replace `[password]` with your desired password. This will create the encrypted `data` file. You can now remove `data_raw`.

Now you should be ready to make it work. Run your web server and access `index.php`. The script will prompt you for your password. If correct you will receive an email with a token that will actually give you access to your passwords. Otherwise you will receive an email warning you that someone might be trying to access your private data.

There is a quick removal feature in case you need it: access `index.php?quickSecure=now` and enter your password. If it is correct, all your files will be deleted immediately.

For maximum security, I added a logger feature, that basically enables only 3 login attempts per IP every 24h. In order to use it, you will need a simple database with the following table:
```
CREATE TABLE logs(
    ip VARCHAR(255) NOT NULL,
    datetime DATETIME NOT NULL,
    step INT NOT NULL,
    status INT(1) NOT NULL
)
```

Finally, note that your password and decrypted data is never stored anywhere. When you try to connect, the script simply tries to decrypt your `data` file with the given password and the script knows whether decryption was successful or not in order to know whether your password was correct or not.
