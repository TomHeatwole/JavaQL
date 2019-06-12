#include <iostream>
#include <stdio.h>
#include <stdlib.h>
#include <unistd.h>
#include <sys/types.h>
#include <string.h>
#include <sys/wait.h>
using namespace std;

int num_passed = 0;
int num_failed = 0;
int read_fd;
int write_fd;
bool pass = true;

bool setup_error(string message) {
    cout << "Failed to begin testing: " << message << endl;
    return 1;
}

// From stackoverflow: https://stackoverflow.com/questions/1583353/how-to-read-exactly-one-line
string read_line() {
    if (!pass) return "";
    string* line;
    static string buffer;
    string::iterator pos;
    while ((pos = find (buffer.begin(), buffer.end(), '\n')) == buffer.end()) {
        char buf [1025];
        int n = read(read_fd, buf, 1024);
        if (n == -1) {
          *line = buffer;
          buffer = "";
          return "failed to read line";
        }
        buf [n] = 0;
        buffer += buf;
    }
    *line = string(buffer.begin(), pos);
    buffer = string(pos + 1, buffer.end());
    return *line;
}

void next_test(string name) {
    if (pass) {
        cout << "PASSED" << endl;
        num_passed++;
    } else {
        cout << "FAILED" << endl;
        pass = true;
        num_failed++;
    }
    if (name != "done") cout << name << " . . . ";
}

void assert_true(bool exp) {
    pass = pass && exp;
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
    }
    close(child_to_parent[1]);
    read_fd = child_to_parent[0];
    write_fd = parent_to_child[1];
    string line;
    cout << "Connect to database . . . ";
    assert_true(read_line() == "Attempting to connect to database...");
    assert_true(read_line() == "Connected successfully");
    assert_true(read_line() == "Loading classes...");
    assert_true(read_line() == "Classes loaded");
    assert_true(read_line() == "");

    next_test("done");
    printf("%i tests passed.\n", num_passed);
    printf("%i tests failed.\n", num_failed);
    return 0;
}

