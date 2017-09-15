cd epfl-os-wp
cd build/httpd
docker build -t camptocamp/httpd .
cd ../mgmt
docker build -t camptocamp/mgmt .

cd jahia2wp
mkdir -p data/srv/.ssh
docker-compose up

blocked by https://github.com/camptocamp/epfl-os-wp/issues/2