# Set the working directory.
cd $(dirname $0)

# Capture the options.
for i in "$@"
do
case $i in
    --php-version=*)
    PHP_VERSION="${i#*=}"
    shift # past argument=value
    ;;
    --sdk-version=*)
    SDK_VERSION="${i#*=}"
    shift # past argument=value
    ;;
    *)
        # unknown option
    ;;
esac
done

# Validate the required options.
if [ "$PHP_VERSION" = "" ] || [ "$SDK_VERSION" = "" ]; then
    cat << EOF
Usage:  sh template.sh --php-version=<PHP_VERSION> --sdk-version=<SDK_VERSION>

OPTIONS:
  --php-version     PHP version
  --sdk-version     Couchbase PHP Client SDK version
EOF
    exit 1
fi

TEMPLATE_FILE="dockerfile-php55.template"
if [ "$PHP_VERSION" = "5.6" ]; then
    TEMPLATE_FILE="dockerfile-php56.template"
elif [ "$PHP_VERSION" = "7.0" ]; then
    TEMPLATE_FILE="dockerfile-php70.template"
fi

# Print the generated Dockerfile.
eval "cat << Dockerfile \

$(cat $TEMPLATE_FILE)

Dockerfile"
