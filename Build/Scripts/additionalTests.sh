#!/usr/bin/env bash

#
# TYPO3 core test runner based on docker.
#

waitFor() {
    local HOST=${1}
    local PORT=${2}
    local TESTCOMMAND="
        COUNT=0;
        while ! nc -z ${HOST} ${PORT}; do
            if [ \"\${COUNT}\" -gt 10 ]; then
              echo \"Can not connect to ${HOST} port ${PORT}. Aborting.\";
              exit 1;
            fi;
            sleep 1;
            COUNT=\$((COUNT + 1));
        done;
    "
    ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name wait-for-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${IMAGE_ALPINE} /bin/sh -c "${TESTCOMMAND}"
}

cleanUp() {
    ATTACHED_CONTAINERS=$(${CONTAINER_BIN} ps --filter network=${NETWORK} --format='{{.Names}}')
    for ATTACHED_CONTAINER in ${ATTACHED_CONTAINERS}; do
        ${CONTAINER_BIN} rm -f ${ATTACHED_CONTAINER} >/dev/null
    done
    ${CONTAINER_BIN} network rm ${NETWORK} >/dev/null
}

handleDbmsOptions() {
    # -a, -d, -i depend on each other. Validate input combinations and set defaults.
    case ${DBMS} in
        mariadb)
            [ -z "${DATABASE_DRIVER}" ] && DATABASE_DRIVER="mysqli"
            if [ "${DATABASE_DRIVER}" != "mysqli" ] && [ "${DATABASE_DRIVER}" != "pdo_mysql" ]; then
                echo "Invalid combination -d ${DBMS} -a ${DATABASE_DRIVER}" >&2
                echo >&2
                echo "Use \".Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            [ -z "${DBMS_VERSION}" ] && DBMS_VERSION="10.3"
            if ! [[ ${DBMS_VERSION} =~ ^(10.3|10.4|10.5|10.6|10.7|10.8|10.9|10.10|10.11|11.0|11.1)$ ]]; then
                echo "Invalid combination -d ${DBMS} -i ${DBMS_VERSION}" >&2
                echo >&2
                echo "Use \".Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            ;;
        mysql)
            [ -z "${DATABASE_DRIVER}" ] && DATABASE_DRIVER="mysqli"
            if [ "${DATABASE_DRIVER}" != "mysqli" ] && [ "${DATABASE_DRIVER}" != "pdo_mysql" ]; then
                echo "Invalid combination -d ${DBMS} -a ${DATABASE_DRIVER}" >&2
                echo >&2
                echo "Use \".Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            [ -z "${DBMS_VERSION}" ] && DBMS_VERSION="8.0"
            if ! [[ ${DBMS_VERSION} =~ ^(8.0)$ ]]; then
                echo "Invalid combination -d ${DBMS} -i ${DBMS_VERSION}" >&2
                echo >&2
                echo "Use \".Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            ;;
        postgres)
            if [ -n "${DATABASE_DRIVER}" ]; then
                echo "Invalid combination -d ${DBMS} -a ${DATABASE_DRIVER}" >&2
                echo >&2
                echo "Use \".Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            [ -z "${DBMS_VERSION}" ] && DBMS_VERSION="10"
            if ! [[ ${DBMS_VERSION} =~ ^(10|11|12|13|14|15)$ ]]; then
                echo "Invalid combination -d ${DBMS} -i ${DBMS_VERSION}" >&2
                echo >&2
                echo "Use \".Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            ;;
        sqlite)
            if [ -n "${DATABASE_DRIVER}" ]; then
                echo "Invalid combination -d ${DBMS} -a ${DATABASE_DRIVER}" >&2
                echo >&2
                echo "Use \".Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            if [ -n "${DBMS_VERSION}" ]; then
                echo "Invalid combination -d ${DBMS} -i ${DATABASE_DRIVER}" >&2
                echo >&2
                echo "Use \".Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
                exit 1
            fi
            ;;
        *)
            echo "Invalid option -d ${DBMS}" >&2
            echo >&2
            echo "Use \".Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
            exit 1
            ;;
    esac
}

cleanBuildFiles() {
    echo -n "Clean builds ... "
    rm -rf \
        Build/JavaScript \
        Build/node_modules \
        Documentation-GENERATED-temp
    echo "done"
}

cleanCacheFiles() {
    echo -n "Clean caches ... "
    rm -rf \
        .cache \
        Build/.cache \
        Build/composer/.cache/ \
        .php-cs-fixer.cache
    echo "done"
}

cleanTestFiles() {
    # test related
    echo -n "Clean test related files ... "
    rm -rf \
        bin/ \
        Build/phpunit \
        public/ \
        typo3temp/ \
        vendor/ \
        var/ \
        composer.lock
    git checkout composer.json
    echo "done"
}

cleanRenderedDocumentationFiles() {
    echo -n "Clean rendered documentation files ... "
    rm -rf \
        typo3/sysext/*/Documentation-GENERATED-temp
    echo "done"
}

loadHelp() {
    # Load help text into $HELP
    read -r -d '' HELP <<EOF
TYPO3 core test runner. Execute acceptance, unit, functional and other test suites in
a container based test environment. Handles execution of single test files, sending
xdebug information to a local IDE and more.

Usage: $0 [options] [file]

Options:
    -s <...>
        Specifies the test suite to run
            - acceptance: main application acceptance tests
            - acceptanceInstall: installation acceptance tests, only with -d mariadb|postgres|sqlite
            - buildCss: execute scss to css builder
            - buildJavascript: execute typescript to javascript builder
            - cgl: test and fix all core php files
            - cglGit: test and fix latest committed patch for CGL compliance
            - cglHeader: test and fix file header for all core php files
            - cglHeaderGit: test and fix latest committed patch for CGL file header compliance
            - checkAnnotations: check php code for allowed annotations
            - checkBom: check UTF-8 files do not contain BOM
            - checkComposer: check composer.json files for version integrity
            - checkExceptionCodes: test core for duplicate exception codes
            - checkExtensionScannerRst: test all .rst files referenced by extension scanner exist
            - checkFilePathLength: test core file paths do not exceed maximum length
            - checkGitSubmodule: test core git has no sub modules defined
            - checkGruntClean: Verify "grunt build" is clean. Warning: Executes git commands! Usually used in CI only.
            - checkIsoDatabase: Verify "updateIsoDatabase.php" does not change anything.
            - checkNamespaceIntegrity: Verify namespace integrity in class and test code files are in good shape.
            - checkPermissions: test some core files for correct executable bits
            - checkRst: test .rst files for integrity
            - checkTestClassFinal: check test case classes are final
            - checkTestMethodsPrefix: check tests methods do not start with "test"
            - clean: clean up build, cache and testing related files and folders
            - cleanBuild: clean up build related files and folders
            - cleanCache: clean up cache related files and folders
            - cleanRenderedDocumentation: clean up rendered documentation files and folders (Documentation-GENERATED-temp)
            - cleanTests: clean up test related files and folders
            - composerInstall: "composer install"
            - composerInstallMax: "composer update", with no platform.php config.
            - composerInstallMin: "composer update --prefer-lowest", with platform.php set to PHP version x.x.0.
            - composerTestDistribution: "composer update" in Build/composer to verify core dependencies
            - composerValidate: "composer validate"
            - functional: PHP functional tests
            - functionalDeprecated: deprecated PHP functional tests
            - lintPhp: PHP linting
            - lintScss: SCSS linting
            - lintTypescript: TS linting
            - lintHtml: HTML linting
            - listExceptionCodes: list core exception codes in JSON format
            - phpstan: phpstan tests
            - phpstanGenerateBaseline: regenerate phpstan baseline, handy after phpstan updates
            - unit (default): PHP unit tests
            - unitDeprecated: deprecated PHP unit tests
            - unitJavascript: JavaScript unit tests
            - unitRandom: PHP unit tests in random order, add -o <number> to use specific seed

    -p <8.1|8.2|8.3>
        Specifies the PHP minor version to be used
            - 8.1 (default): use PHP 8.1
            - 8.2: use PHP 8.2
            - 8.3: use PHP 8.3

    -q
        package to be installed by composer

    -r
        parameters used with composer commands

    -h
        Show this help.

    -v
        Enable verbose script output. Shows variables and docker commands.

Examples:
    # Run install a package with composer
    ./Build/Scripts/additionalTests.sh -p 8.1 -s composerInstallPackage "typo3/cms-core:12.4"

    # Linting scss files
    ./Build/Scripts/additionalTests.sh -s lintScss

    # Linting typescript files
    ./Build/Scripts/additionalTests.sh -s lintTypescript

    # Test build the documentation
    ./Build/Scripts/additionalTests.sh -s buildDocumentation
EOF
}

# Test if docker exists, else exit out with error
if ! type "docker" >/dev/null; then
    echo "This script relies on docker. Please install" >&2
    exit 1
fi

# Option defaults
TEST_SUITE="unit"
DBMS="sqlite"
DBMS_VERSION=""
PHP_VERSION="8.1"
PHP_XDEBUG_ON=0
PHP_XDEBUG_PORT=9003
ACCEPTANCE_HEADLESS=1
EXTRA_TEST_OPTIONS=""
PHPUNIT_RANDOM=""
CGLCHECK_DRY_RUN=""
DATABASE_DRIVER=""
CHUNKS=0
THISCHUNK=0
CONTAINER_BIN="docker"

SCRIPT_VERBOSE=0
COMPOSER_PACKAGE=""
COMPOSER_PARAMETER=""

# Option parsing updates above default vars
# Reset in case getopts has been used previously in the shell
OPTIND=1
# Array for invalid options
INVALID_OPTIONS=()
# Simple option parsing based on getopts (! not getopt)
while getopts ":s:p:q:r:hv" OPT; do
    case ${OPT} in
        s)
            TEST_SUITE=${OPTARG}
            ;;
        p)
            PHP_VERSION=${OPTARG}
            if ! [[ ${PHP_VERSION} =~ ^(8.1|8.2|8.3)$ ]]; then
                INVALID_OPTIONS+=("${OPTARG}")
            fi
            ;;
        q)
            COMPOSER_PACKAGE=${OPTARG}
            ;;
        r)
            COMPOSER_PARAMETER=${OPTARG}
            ;;
        h)
            loadHelp
            echo "${HELP}"
            exit 0
            ;;
        v)
            SCRIPT_VERBOSE=1
            ;;
        \?)
            INVALID_OPTIONS+=("${OPTARG}")
            ;;
        :)
            INVALID_OPTIONS+=("${OPTARG}")
            ;;
    esac
done

# Exit on invalid options
if [ ${#INVALID_OPTIONS[@]} -ne 0 ]; then
    echo "Invalid option(s):" >&2
    for I in "${INVALID_OPTIONS[@]}"; do
        echo "-"${I} >&2
    done
    echo >&2
    echo "Use \"./Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
    exit 1
fi

handleDbmsOptions

COMPOSER_ROOT_VERSION="7.0.1"
HOST_UID=$(id -u)
HOST_PID=$(id -g)
USERSET=""
if [ $(uname) != "Darwin" ]; then
    USERSET="--user $HOST_UID"
fi

# Go to the directory this script is located, so everything else is relative
# to this dir, no matter from where this script is called, then go up two dirs.
THIS_SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null && pwd)"
cd "$THIS_SCRIPT_DIR" || exit 1
cd ../../ || exit 1
CORE_ROOT="${PWD}"

# Create .cache dir: composer and various npm jobs need this.
mkdir -p .cache
mkdir -p typo3temp/var/tests

PHPSTAN_CONFIG_FILE="phpstan.local.neon"
IMAGE_PREFIX="docker.io/"
# Non-CI fetches TYPO3 images (php and nodejs) from ghcr.io
TYPO3_IMAGE_PREFIX="ghcr.io/"
CONTAINER_INTERACTIVE="-it --init"

IS_CORE_CI=0
# ENV var "CI" is set by gitlab-ci. We use it here to distinct 'local' and 'CI' environment.
if [ "${CI}" == "true" ]; then
    IS_CORE_CI=1
    PHPSTAN_CONFIG_FILE="phpstan.ci.neon"
    # In CI, we need to pull images from docker.io for the registry proxy to kick in.
    TYPO3_IMAGE_PREFIX="docker.io/"
    IMAGE_PREFIX=""
    CONTAINER_INTERACTIVE=""
fi


IMAGE_APACHE="${TYPO3_IMAGE_PREFIX}typo3/core-testing-apache24:latest"
IMAGE_PHP="${TYPO3_IMAGE_PREFIX}typo3/core-testing-$(echo "php${PHP_VERSION}" | sed -e 's/\.//'):latest"
IMAGE_NODEJS="${TYPO3_IMAGE_PREFIX}typo3/core-testing-nodejs18:latest"
IMAGE_NODEJS_CHROME="${TYPO3_IMAGE_PREFIX}typo3/core-testing-nodejs18-chrome:latest"
IMAGE_ALPINE="${IMAGE_PREFIX}alpine:3.8"
IMAGE_SELENIUM="${IMAGE_PREFIX}selenium/standalone-chrome:4.11.0-20230801"
IMAGE_REDIS="${IMAGE_PREFIX}redis:4-alpine"
IMAGE_MEMCACHED="${IMAGE_PREFIX}memcached:1.5-alpine"
IMAGE_MARIADB="${IMAGE_PREFIX}mariadb:${DBMS_VERSION}"
IMAGE_MYSQL="${IMAGE_PREFIX}mysql:${DBMS_VERSION}"
IMAGE_POSTGRES="${IMAGE_PREFIX}postgres:${DBMS_VERSION}-alpine"
IMAGE_DOCUMENTATION="ghcr.io/t3docs/render-documentation:v3.0.dev30"
IMAGE_XLIFF="container.registry.gitlab.typo3.org/qa/example-extension:typo3-ci-xliff-lint"

# Detect arm64 to use seleniarm image.
ARCH=$(uname -m)
if [ ${ARCH} = "arm64" ]; then
    IMAGE_SELENIUM="${IMAGE_PREFIX}seleniarm/standalone-chromium:4.1.2-20220227"
    echo "Architecture" ${ARCH} "requires" ${IMAGE_SELENIUM} "to run acceptance tests."
fi

# Set $1 to first mass argument, this is the optional test file or test directory to execute
shift $((OPTIND - 1))
TEST_FILE=${1}

SUFFIX=$(echo $RANDOM)
NETWORK="typo3-core-${SUFFIX}"
${CONTAINER_BIN} network create ${NETWORK} >/dev/null

CONTAINER_COMMON_PARAMS="${CONTAINER_INTERACTIVE} --rm --network $NETWORK --add-host "host.docker.internal:host-gateway" $USERSET -v ${CORE_ROOT}:${CORE_ROOT}"

if [ ${PHP_XDEBUG_ON} -eq 0 ]; then
    XDEBUG_MODE="-e XDEBUG_MODE=off"
    XDEBUG_CONFIG=" "
    PHP_FPM_OPTIONS="-d xdebug.mode=off"
else
    XDEBUG_MODE="-e XDEBUG_MODE=debug -e XDEBUG_TRIGGER=foo"
    XDEBUG_CONFIG="client_port=${PHP_XDEBUG_PORT} client_host=host.docker.internal"
    PHP_FPM_OPTIONS="-d xdebug.mode=debug -d xdebug.start_with_request=yes -d xdebug.client_host=host.docker.internal -d xdebug.client_port=${PHP_XDEBUG_PORT} -d memory_limit=256M"
fi
# if host uid is root, like for example on ci we need to set additional php-fpm command line options
if [ "${HOST_UID}" = 0 ]; then
    PHP_FPM_OPTIONS+=" --allow-to-run-as-root"
fi

# Suite execution
case ${TEST_SUITE} in
    buildDocumentation)
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} -v ${CORE_ROOT}:/project ghcr.io/typo3-documentation/render-guides:latest render Documentation
        SUITE_EXIT_CODE=$?
        ;;
    clean)
        cleanBuildFiles
        cleanCacheFiles
        cleanRenderedDocumentationFiles
        cleanTestFiles
        ;;
    composerInstallPackage)
        COMMAND="[ ${SCRIPT_VERBOSE} -eq 1 ] && set -x; composer require -W -n ${COMPOSER_PARAMETER} ${COMPOSER_PACKAGE};"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-require-package-${SUFFIX} -w ${CORE_ROOT} -e COMPOSER_CACHE_DIR=${CORE_ROOT}/Build/.cache/composer ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    lintPhp)
        COMMAND="[ ${SCRIPT_VERBOSE} -eq 1 ] && set -x; php -v | grep '^PHP'; find Classes/ -name \\*.php -print0 | xargs -0 -n1 -P4 php -dxdebug.mode=off -l > /dev/null;"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name lint-php-${SUFFIX} -w ${CORE_ROOT} ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    lintScss)
        COMMAND="[ ${SCRIPT_VERBOSE} -eq 1 ] && set -x; cd Build; npm ci || exit 1; npm run lint:css;"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name lint-css-${SUFFIX} -w ${CORE_ROOT} -e HOME=${CORE_ROOT}/.cache ${IMAGE_NODEJS} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    lintTypescript)
        COMMAND="[ ${SCRIPT_VERBOSE} -eq 1 ] && set -x; cd Build; npm ci || exit 1; npm run lint:ts;"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name lint-typescript-${SUFFIX} -w ${CORE_ROOT} -e HOME=${CORE_ROOT}/.cache ${IMAGE_NODEJS} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    lintXliff)
        COMMAND="[ ${SCRIPT_VERBOSE} -eq 1 ] && set -x; xmllint --schema /xliff-core-1.2-strict.xsd --noout *.xlf;"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name lint-xliff-${SUFFIX} -w ${CORE_ROOT}/Resources/Private/Language ${IMAGE_XLIFF} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
esac

cleanUp

# Print summary
echo "" >&2
echo "###########################################################################" >&2
echo "Result of ${TEST_SUITE}" >&2
if [[ ${IS_CORE_CI} -eq 1 ]]; then
    echo "Environment: CI" >&2
else
    echo "Environment: local" >&2
fi
echo "PHP: ${PHP_VERSION}" >&2
if [[ "${COMPOSER_PACKAGE}" != "" ]]; then
    echo "Package: ${COMPOSER_PACKAGE}" >&2
fi
if [[ ${SUITE_EXIT_CODE} -eq 0 ]]; then
    echo "SUCCESS" >&2
else
    echo "FAILURE" >&2
fi
echo "###########################################################################" >&2
echo "" >&2

# Exit with code of test suite - This script return non-zero if the executed test failed.
exit $SUITE_EXIT_CODE
