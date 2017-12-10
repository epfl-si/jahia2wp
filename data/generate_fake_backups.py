import os
import random
import datetime

FAKE_DIR = "backups/fake"
END_DATE = datetime.date.today()
START_DATE = END_DATE - datetime.timedelta(days=365)

if not os.path.isdir(FAKE_DIR):
    os.makedirs(FAKE_DIR)


def main():
    for index in range(100):
        random_day = datetime.datetime.fromordinal(random.randint(
            START_DATE.toordinal(), END_DATE.toordinal())) \
            + datetime.timedelta(minutes=random.randint(0, 60*24))
        filename = "{}/test_{}_full{}.".format(
            FAKE_DIR,
            random_day.strftime("%Y%m%d%H%M%S"),
            random.randint(0, 10))
        with open(filename+'tar', 'w') as tar:
            tar.write("content for tar")
        with open(filename+'sql', 'w') as sql:
            sql.write("content for SQL")


if __name__ == '__main__':
    main()
