
FROM python:2.7

COPY fakeprobe.py /fakeprobe.py
COPY requirements.txt /requirements.txt
COPY fakeprobe.docker.ini /fakeprobe.ini

RUN pip -r /requirements.txt

WORKDIR /
CMD python fakeprobe.py
