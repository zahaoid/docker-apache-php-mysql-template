CREATE TABLE contexts (
    entryid INT,
    trcontext VARCHAR(64) NOT NULL,
    arcontext VARCHAR(64) NOT NULL,
    FOREIGN KEY (entryid) REFERENCES entries(id)
);
