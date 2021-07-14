#!/bin/bash

# Exit if any command fails.
set -e

WP_CONTAINER=${1-woocommerce_services_wordpress}
SITE_URL=${WP_URL-"localhost:8050"}

redirect_output() {
	if [[ -z "$DEBUG" ]]; then
        "$@" > /dev/null
    else
        "$@"
    fi
}

# --user xfs forces the wordpress:cli container to use a user with the same ID as the main wordpress container. See:
# https://hub.docker.com/_/wordpress#running-as-an-arbitrary-user
cli()
{
	INTERACTIVE=''
	if [ -t 1 ] ; then
		INTERACTIVE='-it'
	fi
	redirect_output docker run $INTERACTIVE --env-file default.env --rm --user xfs --volumes-from $WP_CONTAINER --network container:$WP_CONTAINER wordpress:cli "$@"
}

set +e
# Wait for containers to be started up before the setup.
# The db being accessible means that the db container started and the WP has been downloaded and the plugin linked
cli wp db check --path=/var/www/html --quiet > /dev/null
while [[ $? -ne 0 ]]; do
	echo "Waiting until the service is ready..."
	sleep 5s
	cli wp db check --path=/var/www/html --quiet > /dev/null
done

# If the plugin is already active then return early
cli wp plugin is-active woocommerce-services > /dev/null
if [[ $? -eq 0 ]]; then
	set -e
	echo
	echo "WC Shipping & Tax is installed and active"
	echo "SUCCESS! You should now be able to access http://${SITE_URL}/wp-admin/"
	echo "You can login by using the username and password both as 'admin'"
	exit 0
fi

set -e

echo
echo "Setting up environment..."
echo

echo "Pulling the WordPress CLI docker image..."
docker pull wordpress:cli > /dev/null

echo "Setting up WordPress..."
cli wp core install \
	--path=/var/www/html \
	--url=$SITE_URL \
	--title=${SITE_TITLE-"WooCommerce Services Dev"} \
	--admin_name=${WP_ADMIN-admin} \
	--admin_password=${WP_ADMIN_PASSWORD-admin} \
	--admin_email=${WP_ADMIN_EMAIL-admin@example.com} \
	--skip-email

echo "Updating WordPress to the latest version..."
cli wp core update --quiet

echo "Updating the WordPress database..."
cli wp core update-db --quiet

echo "Configuring WordPress to work with ngrok (in order to allow creating a Jetpack-WPCOM connection)";
cli config set DOCKER_HOST "\$_SERVER['HTTP_X_ORIGINAL_HOST'] ?? \$_SERVER['HTTP_HOST'] ?? 'localhost'" --raw
cli config set DOCKER_REQUEST_URL "( ! empty( \$_SERVER['HTTPS'] ) ? 'https://' : 'http://' ) . DOCKER_HOST" --raw
cli config set WP_SITEURL DOCKER_REQUEST_URL --raw
cli config set WP_HOME DOCKER_REQUEST_URL --raw

echo "Enabling WordPress debug flags"
cli config set WP_DEBUG true --raw
cli config set WP_DEBUG_DISPLAY true --raw
cli config set WP_DEBUG_LOG true --raw
cli config set SCRIPT_DEBUG true --raw

echo "Enabling Jetpack debug flags"
cli config set JETPACK_AUTOLOAD_DEV true --raw

echo "Enabling WordPress development environment";
cli config set WP_ENVIRONMENT_TYPE development

echo "Enabling WCS&T dev flags"
cli config set WOOCOMMERCE_COM_STAGING false --raw
cli config set WOOCOMMERCE_CONNECT_FREQUENT_FETCH true --raw
cli config set WOOCOMMERCE_CONNECT_DEV_SERVER_URL http://localhost:8085/
cli config set WOOCOMMERCE_CONNECT_SERVER_URL http://host.docker.internal:5000/

echo "Updating permalink structure"
cli wp rewrite structure '/%postname%/'

echo "Installing and activating WooCommerce..."
cli wp plugin install woocommerce --activate

echo "Installing and activating Storefront theme..."
cli wp theme install storefront --activate

echo "Adding basic WooCommerce settings..."
cli wp option set woocommerce_store_address "60 29th Street"
cli wp option set woocommerce_store_address_2 "#343"
cli wp option set woocommerce_store_city "San Francisco"
cli wp option set woocommerce_default_country "US:CA"
cli wp option set woocommerce_store_postcode "94110"
cli wp option set woocommerce_currency "USD"
cli wp option set woocommerce_product_type "both"
cli wp option set woocommerce_allow_tracking "no"
cli wp option set woocommerce_weight_unit "lbs"
cli wp option set woocommerce_dimension_unit "in"

echo "Importing WooCommerce shop pages..."
cli wp wc --user=admin tool run install_pages

echo "Installing and activating the WordPress Importer plugin..."
cli wp plugin install wordpress-importer --activate

echo "Importing some sample data..."
cli wp import wp-content/plugins/woocommerce/sample-data/sample_products.xml --authors=skip

echo "Copying the WooCommerce Services helper plugin..."
mkdir -p docker/wordpress/wp-content/mu-plugins
cp docker/mu-dev.php docker/wordpress/wp-content/mu-plugins/
cp docker/mu-wccon-staging.php docker/wordpress/wp-content/mu-plugins/

echo "Activating the WooCommerce Services plugin..."
cli wp plugin activate woocommerce-services

echo
echo "SUCCESS! You should now be able to access http://${SITE_URL}/wp-admin/"
echo "You can login by using the username and password both as 'admin' and connect your site to Jetpack (use Jurassic Tube or ngrok)"
