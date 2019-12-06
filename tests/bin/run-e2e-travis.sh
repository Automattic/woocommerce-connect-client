#!/usr/bin/env bash
if [[ ${RUN_E2E} == 1 ]]; then
    export WP_BASE_URL="http://localhost:8080"
    export WP_CUSTOMER_USER_NAME=customer
    export WP_CUSTOMER_USER_PW=password
    export WP_ADMIN_USER_NAME=admin
    export WP_ADMIN_USER_PW=password
    export WOOCOMMERCE_SERVICES_CI_TEST_MODE=true
    export WOOCOMMERCE_SERVICES_LOCAL_TEST_MODE=true
    export WP_MYSQL_E2E_DB=wcs_test
    export WP_MYSQL_E2E_USERNAME=root
    export WP_MYSQL_E2E_PASSWORD=
    export WP_MYSQL_E2E_HOST=localhost
fi