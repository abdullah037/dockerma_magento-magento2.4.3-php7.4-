FROM webdevops/php-apache-dev:7.4
# Create app directory
WORKDIR /app
# Install app dependencies
# Copy app source code
COPY . .
RUN composer install
RUN /app/m2install.sh
#RUN bash m2install.sh
#RUN php bin/magento setup:install --admin-firstname=abdullah --admin-lastname=iftikhar --admin-email=iabdullah@focusteck.com --admin-user=abdullah --admin-password='Abdullah@6668' --base-url=http://localhost --base-url-secure=https://localhost --backend-frontname=admin --db-host=mysql --db-name=magento --db-user=root --db-password=root --use-rewrites=1 --language=en_US --currency=USD --timezone=America/New_York --use-secure-admin=0 --admin-use-security-key=1 --session-save=files --use-sample-data
#RUN chmod +x m2install.sh

#Expose port and start application
EXPOSE 80
#ENTRYPOINT ["/m2install.sh"]

