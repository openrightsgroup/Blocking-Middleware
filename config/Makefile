
DT=$(shell date '+%Y%m%d')
RELEASE := 01

build : ${DT}${RELEASE}.json

${DT}${RELEASE}.json : rules.yml
	python3 export.py --date ${DT} --release ${RELEASE} $^ > $@
