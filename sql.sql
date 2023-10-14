DROP TABLE IF EXISTS shift;
CREATE TABLE shift (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    hdn VARCHAR(15),
    pas VARCHAR(15),
    date_7 DATETIME,
    ip VARCHAR(100),
    created TIMESTAMP,
    PRIMARY KEY (id)
);

DROP TABLE IF EXISTS shiftadd;
CREATE TABLE shiftadd (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    add_hdn VARCHAR(15),
    add_pas VARCHAR(15),
    ip VARCHAR(100),
    add_id INT UNSIGNED,
    created TIMESTAMP,
    PRIMARY KEY (id),
    FOREIGN KEY (add_id) REFERENCES shift(id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
);