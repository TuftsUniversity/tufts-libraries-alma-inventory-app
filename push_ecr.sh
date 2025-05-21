VER=0.7
docker tag cedelis/alma-inventory-node:${VER} ${AWS_ACCOUNT}.dkr.ecr.us-east-2.amazonaws.com/alma-inventory-node:${VER}
docker push ${AWS_ACCOUNT}.dkr.ecr.us-east-2.amazonaws.com/alma-inventory-node:${VER}
