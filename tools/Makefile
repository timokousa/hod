#   Copyright (C) 2013 Timo Kousa
#
#   This file is part of HLS On Demand.
#
#   HLS On Demand is free software: you can redistribute it and/or modify
#   it under the terms of the GNU General Public License as published by
#   the Free Software Foundation, either version 3 of the License, or
#   (at your option) any later version.
#
#   HLS On Demand is distributed in the hope that it will be useful,
#   but WITHOUT ANY WARRANTY; without even the implied warranty of
#   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
#   GNU General Public License for more details.
#
#   You should have received a copy of the GNU General Public License
#   along with HLS On Demand.  If not, see <http://www.gnu.org/licenses/>.

CFLAGS += -Wall -g -lcrypto
PREFIX ?= /usr/local

all: segmenter

install: segmenter
	install segmenter $(PREFIX)/bin
	install hod.php $(PREFIX)/bin/hod

uninstall:
	rm $(PREFIX)/bin/segmenter
	rm $(PREFIX)/bin/hod

clean:
	rm -f segmenter

.PHONY: all install uninstall clean
