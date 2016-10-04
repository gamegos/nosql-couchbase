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

# Print the generated Dockerfile.
eval "cat << Dockerfile \

$(cat dockerfile-template)

Dockerfile"
