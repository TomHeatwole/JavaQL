#include <iostream>
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <string.h>
#include <sys/wait.h>
using namespace std;

bool setup_error(string message) {
    cout << "Failed to begin testing: " << message << endl;
    return 1;
}

// From stackoverflow: https://stackoverflow.com/questions/1583353/how-to-read-exactly-one-line
bool read_line(int fd, string* line) {
    static string buffer;
    string::iterator pos;
    while ((pos = find (buffer.begin(), buffer.end(), '\n')) == buffer.end()) {
        char buf [1025];
        int n = read(fd, buf, 1024);
        if (n == -1) {
          *line = buffer;
          buffer = "";
          return false;
        }
        buf [n] = 0;
        buffer += buf;
    }
    *line = string(buffer.begin(), pos);
    buffer = string(pos + 1, buffer.end());
    return true;
}

int main() {
    int parent_to_child[2];
    int child_to_parent[2];
    if (pipe(parent_to_child) == -1) return setup_error("pipe failure");
    if (pipe(child_to_parent) == -1) return setup_error("pipe failure");
    pid_t p = fork();
    if (p < -1) return setup_error("fork failure");
    else if (p == 0) {
        close(parent_to_child[1]);
        dup2(child_to_parent[1], 1);
        dup2(parent_to_child[0], 0);
        char line[1024];
        string name = "./t";
        char buff[4];
        strcpy(buff, name.c_str());
        char* argv[] = {buff};
        execv("t", argv);
    } else {
        close(child_to_parent[1]);
        string line;
        read_line(child_to_parent[0], &line);
        cout << line << "\n";
        read_line(child_to_parent[0], &line);
        cout << line << "\n";
        read_line(child_to_parent[0], &line);
        cout << line << "\n";
        read_line(child_to_parent[0], &line);
        cout << line << "\n";
    }
    return 0;
}

