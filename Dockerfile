FROM node:8

RUN apt update
RUN uname -a
#RUN apt search openjdk-8-jdk-headless
RUN apt install -y openjdk-8-jdk-headless
ENV JAVA_HOME=/usr/lib/jvm/java-1.8-openjdk
ENV PATH="$JAVA_HOME/bin:${PATH}"

RUN java -version
RUN javac -version

# Create app directory
WORKDIR /usr/src/app

# Install app dependencies
# A wildcard is used to ensure both package.json AND package-lock.json are copied
# where available (npm@5+)
COPY webapp/* /usr/src/app/
COPY webapp/views/* /usr/src/app/views/
COPY webapp/images/* /usr/src/app/images/
COPY node/*.js /usr/src/app/
COPY node/package*.json ./
COPY gsheet.prop.json.template ./gsheet.prop.json

RUN npm install
RUN npm install request
# If you are building your code for production
# RUN npm install --only=production

EXPOSE 80
CMD [ "npm", "start" ]
