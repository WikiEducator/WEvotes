--
-- Schema for the WEvotes extension
--
CREATE TABLE /*_*/wevotes (
  -- The voting group ID (pid)
  wev_pid varchar(64) NOT NULL,

  -- The vote item ID (vid)
  wev_vid varchar(64) NOT NULL,

  -- The username of the voter
  wev_user_name varchar(255) NOT NULL,

  -- The vote value (e.g. -1, 0, 1)
  wev_vote int NOT NULL,

  -- The page ID the voting group appears on
  wev_page int unsigned NOT NULL DEFAULT 0,

  -- Timestamp of when the vote was cast
  wev_timestamp varchar(28) NOT NULL,

  PRIMARY KEY (wev_pid, wev_vid, wev_user_name)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/wev_pid_user ON /*_*/wevotes (wev_pid, wev_user_name);

