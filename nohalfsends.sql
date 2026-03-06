CREATE DATABASE NHS;
USE NHS;



/* Create Table */
CREATE TABLE User (
    userid INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50),
    surname VARCHAR(50),
    username VARCHAR(50) UNIQUE,
    description TEXT,
    userimage VARCHAR(255),
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    role ENUM('user','admin') DEFAULT 'user',
    registrationdate DATETIME
);

CREATE TABLE Sport (
    sportid INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50),
    description TEXT
);

CREATE TABLE Club (
    clubid INT AUTO_INCREMENT PRIMARY KEY,
    sportid INT,
    name VARCHAR(100),
    description TEXT,
    clubimage VARCHAR(255),
    creationdate DATETIME,
    FOREIGN KEY (sportid) REFERENCES Sport(sportid)
);

CREATE TABLE UserClub (
    userid INT,
    clubid INT,
    joindate DATETIME,
    admin BOOLEAN DEFAULT FALSE,
    PRIMARY KEY(userid, clubid),
    FOREIGN KEY (userid) REFERENCES User(userid),
    FOREIGN KEY (clubid) REFERENCES Club(clubid)
);

CREATE TABLE SportUser (
    userid INT,
    sportid INT,
    PRIMARY KEY(userid, sportid),
    FOREIGN KEY (userid) REFERENCES User(userid),
    FOREIGN KEY (sportid) REFERENCES Sport(sportid)
);

CREATE TABLE Activity (
    activityid INT AUTO_INCREMENT PRIMARY KEY,
    userid INT,
    sportid INT,
    name VARCHAR(100),
    activitydate DATETIME,
    duration INT,
    avgheartrate INT,
    maxheartrate INT,
    calories INT,
    description TEXT,
    FOREIGN KEY (userid) REFERENCES User(userid),
    FOREIGN KEY (sportid) REFERENCES Sport(sportid)
);

-- RUN
CREATE TABLE Run (
    activityid INT PRIMARY KEY,
    distance DECIMAL(6,2),
    pace DECIMAL(5,2),
    cadence INT,
    FOREIGN KEY (activityid) REFERENCES Activity(activityid)
);

-- CYCLING
CREATE TABLE Cycling (
    activityid INT PRIMARY KEY,
    distance DECIMAL(6,2),
    elevation INT,
    type ENUM('mountain bike','road','gravel'),
    avgpower INT,
    maxpower INT,
    cadence INT,
    avgspeed DECIMAL(5,2),
    maxspeed DECIMAL(5,2),
    FOREIGN KEY (activityid) REFERENCES Activity(activityid)
);

-- EXCURSION
CREATE TABLE Excursion (
    activityid INT PRIMARY KEY,
    distance DECIMAL(6,2),
    elevation INT,
    pace DECIMAL(5,2),
    FOREIGN KEY (activityid) REFERENCES Activity(activityid)
);

-- GYM
CREATE TABLE Gym (
    activityid INT PRIMARY KEY,
    type VARCHAR(50),
    FOREIGN KEY (activityid) REFERENCES Activity(activityid)
);

-- SKI (specializzazione di Activity)
CREATE TABLE Ski (
    activityid INT PRIMARY KEY,
    distance DECIMAL(6,2),
    elevation INT,
    avgspeed DECIMAL(5,2),
    FOREIGN KEY (activityid) REFERENCES Activity(activityid)
);

-- SKI TOURING
CREATE TABLE Touring (
    activityid INT PRIMARY KEY,
    pace DECIMAL(5,2),
    FOREIGN KEY (activityid) REFERENCES Ski(activityid)
);

-- SKI CROSS COUNTRY
CREATE TABLE CrossCountry (
    activityid INT PRIMARY KEY,
    pace DECIMAL(5,2),
    technique ENUM('skating','classic'),
    FOREIGN KEY (activityid) REFERENCES Ski(activityid)
);

-- SKI ALPINE
CREATE TABLE Alpine (
    activityid INT PRIMARY KEY,
    descentsnr INT,
    maxspeed DECIMAL(5,2),
    FOREIGN KEY (activityid) REFERENCES Ski(activityid)
);

-- SWIMMING
CREATE TABLE Swimming (
    activityid INT PRIMARY KEY,
    type VARCHAR(50),
    distance INT,
    pace DECIMAL(5,2),
    FOREIGN KEY (activityid) REFERENCES Activity(activityid)
);

CREATE TABLE ActivityLike (
    userid INT,
    activityid INT,
    PRIMARY KEY(userid, activityid),
    FOREIGN KEY (userid) REFERENCES User(userid),
    FOREIGN KEY (activityid) REFERENCES Activity(activityid)
);

CREATE TABLE ActivityPhoto (
    activityphotoid INT AUTO_INCREMENT PRIMARY KEY,
    activityid INT,
    url VARCHAR(255),
    FOREIGN KEY (activityid) REFERENCES Activity(activityid)
);

CREATE TABLE Follow (
    followerid INT,
    followedid INT,
    followdate DATETIME,
    PRIMARY KEY(followerid, followedid),
    FOREIGN KEY (followerid) REFERENCES User(userid),
    FOREIGN KEY (followedid) REFERENCES User(userid)
);

CREATE TABLE ActivityComment (
    activitycommentid INT AUTO_INCREMENT PRIMARY KEY,
    userid INT,
    activityid INT,
    text TEXT,
    commentdate DATETIME,
    FOREIGN KEY (userid) REFERENCES User(userid),
    FOREIGN KEY (activityid) REFERENCES Activity(activityid)
);

CREATE TABLE Advice (
    adviceid INT AUTO_INCREMENT PRIMARY KEY,
    authorid INT,
    title VARCHAR(200),
    content TEXT,
    createdate DATETIME,
    category ENUM('nutrition','training','recovery'),
    FOREIGN KEY (authorid) REFERENCES User(userid)
);

CREATE TABLE AdviceComment (
    advicecommentid INT AUTO_INCREMENT PRIMARY KEY,
    userid INT,
    adviceid INT,
    text TEXT,
    commentdate DATETIME,
    FOREIGN KEY (userid) REFERENCES User(userid),
    FOREIGN KEY (adviceid) REFERENCES Advice(adviceid)
);

CREATE TABLE AdvicePhoto (
    advicephotoid INT AUTO_INCREMENT PRIMARY KEY,
    adviceid INT,
    url VARCHAR(255),
    FOREIGN KEY (adviceid) REFERENCES Advice(adviceid)
);

CREATE TABLE AdviceLike (
    userid INT,
    adviceid INT,
    PRIMARY KEY(userid, adviceid),
    FOREIGN KEY (userid) REFERENCES User(userid),
    FOREIGN KEY (adviceid) REFERENCES Advice(adviceid)
);


/* Inserting Into Table*/ 
