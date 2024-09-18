#!/usr/bin/python
# -*- coding: utf-8 -*-
import sys
import os
#Start by importing ChemSpider:
from chemspipy import ChemSpider

#Then connect to ChemSpider by creating a ChemSpider instance using your security token:
cs = ChemSpider('xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx')

def returnIdChemical(compoundName):
    arrayIds=[]
    for result in cs.search(compoundName):
        #help(result)
        id=result.csid
        #image_url=result.image_url
        arrayIds.append(id)
    try:
        return arrayIds[0]
    except:
        return None


try:
    compoundName=sys.argv[1]
except :
    print None
    sys.exit()
print returnIdChemical(compoundName)

