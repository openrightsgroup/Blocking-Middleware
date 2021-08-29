
FROM python:2.7

COPY requirements.txt /requirements.txt

RUN pip install -r /requirements.txt

COPY fakeprobe.py /fakeprobe.py
COPY fakeprobe.docker.ini /fakeprobe.ini

WORKDIR /
CMD python fakeprobe.py
