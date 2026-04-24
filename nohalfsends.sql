CREATE DATABASE NHS;
USE NHS;

/* Create Table*/
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

ALTER TABLE Sport
ADD COLUMN sportimage VARCHAR(255);

CREATE TABLE SportSpecialization (
    specializationid INT AUTO_INCREMENT PRIMARY KEY,
    sportid INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    description VARCHAR(255),
    FOREIGN KEY (sportid) REFERENCES Sport(sportid)
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

INSERT INTO Sport(name, description) VALUES
    ('Run', 'A sport of running on roads or trails.'),
    ('Cycling', 'A sport of riding bikes on roads or mountains.'),
    ('Excursion', 'Walking or hiking in nature.'),
    ('Gym', 'Physical training in a gym.'),
    ('Ski', 'A snow sport using skis.'),
    ('Swimming', 'Swimming in pools or open water.');

INSERT INTO SportSpecialization(sportid, name, description) VALUES
    ((SELECT sportid FROM Sport WHERE name = 'Ski'), 'Touring', 'Backcountry skiing on fresh snow.'),
    ((SELECT sportid FROM Sport WHERE name = 'Ski'), 'CrossCountry', 'Cross-country skiing on groomed trails.'),
    ((SELECT sportid FROM Sport WHERE name = 'Ski'), 'Alpine', 'Downhill skiing on slopes.');

UPDATE Sport
SET sportimage = 'images/sports/run.svg'
WHERE name = 'Run';

UPDATE Sport
SET sportimage = 'images/sports/cycling.svg'
WHERE name = 'Cycling';

UPDATE Sport
SET sportimage = 'images/sports/excursion.svg'
WHERE name = 'Excursion';

UPDATE Sport
SET sportimage = 'images/sports/gym.svg'
WHERE name = 'Gym';

UPDATE Sport
SET sportimage = 'images/sports/ski.svg'
WHERE name = 'Ski';

UPDATE Sport
SET sportimage = 'images/sports/swimming.svg'
WHERE name = 'Swimming';

/* View */ 

use nhs;

--user basic info
CREATE VIEW v_user_profile AS
SELECT
    u.userid,
    u.name,
    u.surname,
    u.username,
    u.description,
    u.userimage,
    u.email,
    u.registrationdate
FROM User u;

--praticed sports
CREATE VIEW v_user_sports AS
SELECT
    su.userid,
    s.sportid,
    s.name AS sport_name,
    s.sportimage
FROM SportUser su
JOIN Sport s ON su.sportid = s.sportid;

--attività recenti
CREATE VIEW v_user_activities AS
SELECT
    a.activityid,
    a.userid,
    a.sportid,
    s.name AS sport_name,
    a.name AS activity_name,
    a.activitydate,
    a.duration
FROM Activity a
JOIN Sport s ON a.sportid = s.sportid;

--club details with member counts and admin info
CREATE VIEW v_club_detail AS
SELECT
    c.clubid,
    c.sportid,
    c.name,
    c.description,
    c.clubimage,
    c.creationdate,
    s.name AS sport_name,
    s.sportimage,
    (SELECT COUNT(*) FROM UserClub WHERE clubid = c.clubid) AS member_count,
    (SELECT COUNT(a.activityid) 
     FROM UserClub uc 
     LEFT JOIN Activity a ON a.userid = uc.userid 
     WHERE uc.clubid = c.clubid) AS activity_count,
    (SELECT MAX(a.activitydate) 
     FROM UserClub uc 
     LEFT JOIN Activity a ON a.userid = uc.userid 
     WHERE uc.clubid = c.clubid) AS last_activity_date,
    (SELECT u.userid FROM UserClub uc2
     INNER JOIN User u ON u.userid = uc2.userid
     WHERE uc2.clubid = c.clubid AND uc2.admin = 1
     ORDER BY uc2.joindate ASC, uc2.userid ASC
     LIMIT 1) AS creator_id,
    (SELECT u.username FROM UserClub uc2
     INNER JOIN User u ON u.userid = uc2.userid
     WHERE uc2.clubid = c.clubid AND uc2.admin = 1
     ORDER BY uc2.joindate ASC, uc2.userid ASC
     LIMIT 1) AS creator_username
FROM Club c
LEFT JOIN Sport s ON s.sportid = c.sportid;

--club members with their roles
CREATE VIEW v_club_members AS
SELECT
    uc.clubid,
    uc.userid,
    u.username,
    u.name,
    u.surname,
    u.userimage,
    uc.joindate,
    uc.admin,
    CASE 
        WHEN uc.admin = 1 THEN 'admin'
        ELSE 'member'
    END AS role
FROM UserClub uc
INNER JOIN User u ON u.userid = uc.userid
ORDER BY uc.admin DESC, uc.joindate ASC;

--user club role check
CREATE VIEW v_user_club_role AS
SELECT
    uc.userid,
    uc.clubid,
    uc.joindate,
    uc.admin,
    c.name AS club_name,
    CASE 
        WHEN uc.admin = 1 THEN 'admin'
        ELSE 'member'
    END AS role
FROM UserClub uc
INNER JOIN Club c ON c.clubid = uc.clubid;

--club statistics summary
CREATE VIEW v_club_stats AS
SELECT
    c.clubid,
    c.name,
    COUNT(DISTINCT uc.userid) AS total_members,
    COUNT(DISTINCT a.activityid) AS total_activities,
    COUNT(DISTINCT CASE WHEN uc.admin = 1 THEN uc.userid END) AS admin_count,
    MAX(a.activitydate) AS last_activity_date
FROM Club c
LEFT JOIN UserClub uc ON uc.clubid = c.clubid
LEFT JOIN Activity a ON a.userid = uc.userid
GROUP BY c.clubid, c.name;


/* Stored Procedures */ 
DELIMITER $$

CREATE PROCEDURE sp_update_user_profile (
    IN p_userid INT,
    IN p_name VARCHAR(50),
    IN p_surname VARCHAR(50),
    IN p_description TEXT,
    IN p_userimage VARCHAR(255),
    IN p_weight DECIMAL(5,2),
    IN p_address VARCHAR(255)
)
BEGIN
    UPDATE User
    SET
        name = p_name,
        surname = p_surname,
        description = p_description,
        userimage = p_userimage,
        weight = p_weight,
        address = p_address
    WHERE userid = p_userid;
END$$

DELIMITER ;